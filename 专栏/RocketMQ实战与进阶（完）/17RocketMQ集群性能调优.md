
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            17 RocketMQ 集群性能调优
                            前言

本篇从系统参数和集群参数两个维度对 RocketMQ 集群进行优化，目的在于 RocketMQ 运行的更平稳。平稳往往比单纯提高 TPS 更重要，文中基于实际生产环境运行情况给出，另外在后面文章中会介绍由于参数设置而引发集群不稳定，业务受到影响的踩坑案例。

系统参数调优

在解压 RocketMQ 安装包后，在 bin 目录中有个 os.sh 的文件，该文件由 RocketMQ 官方推荐系统参数配置。通常这些参数可以满足系统需求，也可以根据情况进行调整。需要强调的是不要使用 Linux 内核版本 2.6 及以下版本，建议使用 Linux 内核版本在 3.10 及以上，如果使用 CentOS，可以选择 CentOS 7 及以上版本。选择 Linux 内核版本 2.6 出现的问题会在后面踩坑案例中提到。

最大文件数

设置用户的打开的最多文件数：

vim /etc/security/limits.conf
# End of file
baseuser soft nofile 655360
baseuser hard nofile 655360
* soft nofile 655360
* hard nofile 655360



系统参数设置

系统参数的调整以官方给出的为主，下面对各个参数做个说明。设置时可以直接执行 sh os.sh 完成系统参数设定，也可以编辑 vim /etc/sysctl.conf 文件手动添加如下内容，添加后执行 sysctl -p 让其生效。

vm.overcommit_memory=1
vm.drop_caches=1
vm.zone_reclaim_mode=0
vm.max_map_count=655360
vm.dirty_background_ratio=50
vm.dirty_ratio=50
vm.dirty_writeback_centisecs=360000
vm.page-cluster=3
vm.swappiness=1



参数说明：




参数
含义





overcommit_memory
是否允许内存的过量分配 overcommit_memory=0 当用户申请内存的时候，内核会去检查是否有这么大的内存空间 overcommit_memory=1 内核始终认为，有足够大的内存空间，直到它用完了为止 overcommit_memory=2 内核禁止任何形式的过量分配内存



drop_caches
写入的时候，内核会清空缓存，腾出内存来，相当于 sync drop_caches=1 会清空页缓存，就是文件 drop_caches=2 会清空 inode 和目录树 drop_caches=3 都清空



zone_reclaim_mode
zone_reclaim_mode=0 系统会倾向于从其他节点分配内存 zone_reclaim_mode=1 系统会倾向于从本地节点回收 Cache 内存



max_map_count
定义了一个进程能拥有的最多的内存区域，默认为 65536



dirty_background_ratio/dirty_ratio
当 dirty cache 到了多少的时候，就启动 pdflush 进程，将 dirty cache 写回磁盘 当有 dirty_background_bytes/dirty_bytes 存在的时候，dirty_background_ratio/dirty_ratio 是被自动计算的



dirty_writeback_centisecs
pdflush 每隔多久，自动运行一次（单位是百分之一秒）



page-cluster
每次 swap in 或者 swap out 操作多少内存页为 2 的指数 page-cluster=0 表示 1 页 page-cluster=1 表示 2 页 page-cluster=2 表示 4 页 page-cluster=3 表示 8 页



swappiness
swappiness=0 仅在内存不足的情况下，当剩余空闲内存低于 vm.min_free_kbytes limit 时，使用交换空间 swappiness=1 内核版本 3.5 及以上、Red Hat 内核版本 2.6.32-303 及以上，进行最少量的交换，而不禁用交换 swappiness=10 当系统存在足够内存时，推荐设置为该值以提高性能 swappiness=60 默认值 swappiness=100 内核将积极的使用交换空间




集群参数调优

生产环境配置

下面列出一份在生产环境使用的配置文件，并说明其参数所表示的含义，只需要稍加修改集群名称即可作为生产环境啊配置使用。

配置示例：

brokerClusterName=testClusterA
brokerName=broker-a
brokerId=0
listenPort=10911
namesrvAddr=x.x.x.x:9876;x.x.x.x::9876
defaultTopicQueueNums=16
autoCreateTopicEnable=false
autoCreateSubscriptionGroup=false
deleteWhen=04
fileReservedTime=48
mapedFileSizeCommitLog=1073741824
mapedFileSizeConsumeQueue=50000000
destroyMapedFileIntervalForcibly=120000
redeleteHangedFileInterval=120000
diskMaxUsedSpaceRatio=88
storePathRootDir=/data/rocketmq/store
storePathCommitLog=/data/rocketmq/store/commitlog
storePathConsumeQueue=/data/rocketmq/store/consumequeue
storePathIndex=/data/rocketmq/store/index
storeCheckpoint=/data/rocketmq/store/checkpoint
abortFile=/data/rocketmq/store/abort
maxMessageSize=65536
flushCommitLogLeastPages=4
flushConsumeQueueLeastPages=2
flushCommitLogThoroughInterval=10000
flushConsumeQueueThoroughInterval=60000
brokerRole=ASYNC_MASTER
flushDiskType=ASYNC_FLUSH
maxTransferCountOnMessageInMemory=1000
transientStorePoolEnable=false
warmMapedFileEnable=false
pullMessageThreadPoolNums=128
slaveReadEnable=true
transferMsgByHeap=true
waitTimeMillsInSendQueue=1000



参数说明：




参数
含义





brokerClusterName
集群名称



brokerName
broker 名称



brokerId
0 表示 Master 节点



listenPort
broker 监听端口



namesrvAddr
namesrvAddr 地址



defaultTopicQueueNums
创建 Topic 时默认的队列数量



autoCreateTopicEnable
是否允许自动创建主题，生产环境建议关闭，非生产环境可以开启



autoCreateSubscriptionGroup
是否允许自动创建消费组，生产环境建议关闭，非生产环境可以开启



deleteWhen
清理过期日志时间，04 表示凌晨 4 点开始清理



fileReservedTime
日志保留的时间单位小时，48 即 48 小时，保留 2 天



mapedFileSizeCommitLog
日志文件大小



mapedFileSizeConsumeQueue
ConsumeQueue 文件大小



destroyMapedFileIntervalForcibly




redeleteHangedFileInterval




diskMaxUsedSpaceRatio
磁盘最大使用率，超过使用率会发起日志清理操作



storePathRootDir
RocketMQ 日志等数据存储的根目录



storePathCommitLog
CommitLog 存储目录



storePathConsumeQueue
ConsumeQueue 存储目录



storePathIndex
索引文件存储目录



storeCheckpoint
checkpoint 文件存储目录



abortFile
abort 文件存储目录



maxMessageSize
单条消息允许的最大字节



flushCommitLogLeastPages
未 flush 的消息大小超过设置页时，才执行 flush 操作；一页大小为 4K



flushConsumeQueueLeastPages
未 flush 的消费队列大小超过设置页时，才执行 flush 操作；一页大小为 4K



flushCommitLogThoroughInterval
两次执行消息 flush 操作的间隔时间，默认为 10 秒



flushConsumeQueueThoroughInterval
两次执行消息队列 flush 操作的间隔时间，默认为 60 秒



brokerRole
broker 角色 ASYNC_MASTER 异步复制的 Master 节点 SYNC_MASTER 同步复制的 Master 节点 SLAVE 从节点



flushDiskType
刷盘类型 ASYNC_FLUSH 异步刷盘 SYNC_FLUSH 同步刷盘



maxTransferCountOnMessageInMemory
消费时允许一次拉取的最大消息数



transientStorePoolEnable
是否开启堆外内存传输



warmMapedFileEnable
是否开启文件预热



pullMessageThreadPoolNums
拉取消息线程池大小



slaveReadEnable
是否开启允许从 Slave 节点读取消息 内存的消息大小占物理内存的比率，当超过默认 40%会从 slave 的 0 节点读取 通过 accessMessageInMemoryMaxRatio 设置内存的消息大小占物理内存的比率



transferMsgByHeap
消息消费时是否从堆内存读取



waitTimeMillsInSendQueue
发送消息时在队列中等待时间，超过会抛出超时错误




调优建议

对 Broker 的几个属性可能影响到集群性能的稳定性，下面进行特别说明。

1. 开启异步刷盘

除了一些支付类场景、或者 TPS 较低的场景（例如：TPS 在 2000 以下）生产环境建议开启异步刷盘，提高集群吞吐。

flushDiskType=ASYNC_FLUSH



2. 开启 Slave 读权限

消息占用物理内存的大小通过 accessMessageInMemoryMaxRatio 来配置默认为 40%；如果消费的消息不在内存中，开启 slaveReadEnable 时会从 slave 节点读取；提高 Master 内存利用率。

slaveReadEnable=true



3. 消费一次拉取消息数量

消费时一次拉取的数量由 broker 和 consumer 客户端共同决定，默认为 32 条。Broker 端参数由 maxTransferCountOnMessageInMemory 设置。consumer 端由 pullBatchSize 设置。Broker 端建议设置大一些，例如 1000，给 consumer 端留有较大的调整空间。

maxTransferCountOnMessageInMemory=1000



4. 发送队列等待时间

消息发送到 Broker 端，在队列的等待时间由参数 waitTimeMillsInSendQueue 设置，默认为 200ms。建议设置大一些，例如：1000ms~5000ms。设置过短，发送客户端会引起超时。

waitTimeMillsInSendQueue=1000



5. 主从异步复制

为提高集群性能，在生成环境建议设置为主从异步复制，经过压力测试主从同步复制性能过低。

brokerRole=ASYNC_MASTER



6. 提高集群稳定性

为了提高集群稳定性，对下面三个参数进行特别说明，在后面踩坑案例中也会提到。

关闭堆外内存：

transientStorePoolEnable=false



关闭文件预热：

warmMapedFileEnable=false



开启堆内传输：

transferMsgByHeap=true


                        
                        
                            