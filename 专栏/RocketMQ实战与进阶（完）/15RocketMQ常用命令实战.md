
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            15 RocketMQ 常用命令实战
                            本篇整理在运维 RocketMQ 集群时的常用命令，明白命令的含义，在集群运维时得心应手，下面命令均在实际环境中执行过。

集群命令汇总

集群列表

命令 clusterList 用于查看集群各个节点的运行情况。可以看到该集群中有几个节点、主节点还是从节点、以及每个节点的写入 TPS 和读出的 TPS 等。

命令示例：

$ bin/mqadmin clusterList -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
#Cluster Name     #Broker Name            #BID  #Addr                  #Version                #InTPS(LOAD)       #OutTPS(LOAD) #PCWait(ms) #Hour #SPACE
fat_mq            fat_mq_c                0     x.x.x.x:10911    V4_7_0                 262.95(0,0ms)       259.85(0,0ms)          0 55.09 0.3130



字段含义




名称
含义





-n
Nameserver 地址



Cluster Name
集群名称



Broker Name
节点 Broker 名称



BID
Broker ID （0 为主节点，从节点非 0 表示）



Addr
节点地址（ip:port）



Version
RocketMQ 的版本号



InTPS
节点每秒写入的消息数量



OutTPS
节点每秒读出的消息数量



PCWait
pageCacheLockTimeMills（消息落盘会加锁，当前时间与最后一次加锁的差值）



Hour
磁盘存储多久的有效消息（当前时间与磁盘存储最早的一条消息时间戳的差值）



SPACE
磁盘已使用的占比




集群中资源吞吐

命令 statsAll 可以查看集群中所有主题/消费组的实时吞吐情况。

命令示例

$ bin/mqadmin statsAll -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
#Topic                            #Consumer Group                  #Accumulation      #InTPS     #OutTPS   #InMsg24Hour  #OutMsg24Hour
trade_eticket_created_topic       trade_eticket_created_consumer              0        0.00        0.00              0              0



字段含义




名称
含义





-n
Nameserver 地址



-a
只打印活动的主题



-t
只打印指定的主题



Topic
主题名称



Consumer Group
消费组名称



Accumulation
消息堆积数量



InTPS
该主题每秒写入的消息数量



OutTPS
该消费组每秒消费的消息数量



InMsg24Hour
该主题 24 小时写入的消息总数



OutMsg24Hour
该消费组 24 小时消费的消息总数




主题命令汇总

主题列表

通过 topicList 列出集群中的所有主题。

命令示例：

$ bin/mqadmin topicList -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
mq_demo1_topic
mq_demo1_topic
mq_demo2_topic
...



字段含义：




名称
含义





-n
Nameserver 地址




主题创建/修改

使用 updateTopic 创建主题，也可以用该命令修改主题配置，例如：队列数量、权限等。

命令示例：

$ bin/mqadmin updateTopic -n x.x.x.x:9876 -c fat_mq -t mq_demo_topic
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
create topic to x.x.x.x:10911 success.
TopicConfig [topicName=mq_demo_topic, readQueueNums=8, writeQueueNums=8, perm=RW-, topicFilterType=SINGLE_TAG, topicSysFlag=0, order=false]



字段含义：




名称
含义





-n
Nameserver 地址



-c
集群名称



-t
要创建的 Topic 名称



topicName
主题名称



readQueueNums
读队列数量



writeQueueNums
写队列数量



perm
主题权限 RW 表示该主题拥有读写权限



topicFilterType
消息过滤类型



topicSysFlag
主题系统标记



order
是否有序主题




主题路由

使用 topicRoute 命令可以查看 Topic 的路由信息，队列所在的 Broker 以及 Broker 所在的集群等。

命令示例：

$ bin/mqadmin topicRoute -n x.x.x.x:9876 -t mq_demo_topic
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
{
    "brokerDatas":[
        {
            "brokerAddrs":{0:"x.x.x.x:10911"
            },
            "brokerName":"fat_mq_c",
            "cluster":"fat_mq"
        }
    ],
    "filterServerTable":{},
    "queueDatas":[
        {
            "brokerName":"fat_mq_c",
            "perm":6,
            "readQueueNums":8,
            "topicSynFlag":0,
            "writeQueueNums":8
        }
    ]
}



字段含义：




名称
含义





-n
Nameserver 地址



-t
主题名称



brokerDatas
broker 信息地址、节点名称、所在集群



queueDatas
队列数量、队列所在的 broker、权限等




主题状态

使用 topicStatus 查看主题状态情况，例如：最小偏移量、最大偏移量、最新更新时间等。

命令示例：

$ bin/mqadmin topicStatus -n x.x.x.x:9876 -t mq_demo_topic
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
#Broker Name                      #QID  #Min Offset           #Max Offset             #Last Updated
fat_mq_c                          0     6                     10                      2020-07-24 14:29:57,707
fat_mq_c                          1     4                     8                       2020-07-24 14:31:32,213
fat_mq_c                          2     20                    22                      2020-07-24 14:35:52,752
fat_mq_c                          3     14                    20                      2020-07-24 14:28:34,287



字段含义：




名称
含义





-n
NameServer 地址



-t
主题名称



Broker Name
节点名称



QID
Queue ID 队列编号



Min Offset
该队列最小偏移量



Max Offset
该队列最大偏移量



Last Updated
最新写入消息的时间戳




主题权限

可以通过 updateTopicPerm 修改主题的权限，有三种类型：写权限用 2 表示、读权限用 4 表示、读写权限用 6 表示。下面示例中将主题从读写权限变更为写权限。

命令示例：

$ bin/mqadmin updateTopicPerm -c fat_mq -t mq_demo_topic -p 2 -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
update topic perm from 6 to 2 in x.x.x.x:10911 success.



字段含义：




名称
含义





-c
集群名称



-t
主题名称



-p
权限（2:W，4:R，6:WR）



-n
NameServer 地址




主题删除

通过 deleteTopic 删除主题，可以通过该命令对废弃主题进行删除。

命令示例：

$ bin/mqadmin deleteTopic -n x.x.x.x:9876 -t mq_demo_topic -c fat_mq
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
delete topic [mq_demo_topic] from cluster [fat_mq] success.
delete topic [mq_demo_topic] from NameServer success.



字段含义：




名称
含义





-n
Nameserver 地址



-t
主题名称



-c
集群名称




消费组命令汇总

消费组创建

通过 updateSubGroup 可以创建消费组，创建成功会返回该消费组的配置信息。

命令示例：

$ bin/mqadmin updateSubGroup -n x.x.x.x:9876 -c fat_mq -g mq_demo_consumer
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
create subscription group to x.x.x.x:10911 success.
SubscriptionGroupConfig [groupName=mq_demo_consumer, consumeEnable=true, consumeFromMinEnable=false, consumeBroadcastEnable=false, retryQueueNums=1, retryMaxTimes=16, brokerId=0, whichBrokerWhenConsumeSlowly=1, notifyConsumerIdsChangedEnable=true]



字段含义：




名称
含义





-n
Nameserver 地址



-c
集群名称



-g
消费组名称



groupName
消费组名称



consumeEnable
是否开启消费，默认开启



consumeFromMinEnable
是否从最小位点消费，默认 false



consumeBroadcastEnable
是否开启广播消费，默认 false



retryQueueNums
重试队列数量，默认为 1



retryMaxTimes
消费重试次数，默认 16 次



brokerId
消费组所在的 brokerId



whichBrokerWhenConsumeSlowly
当 Master 节点消费慢时，默认在从节点 ID 为 1 的 broker 消费




消费者状态

通过 consumerStatus 可以查看各个消费者的情况，包括版本、消费组名称等。

命令示例：

$ bin/mqadmin consumerStatus -g mq_demo_consumer -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
001  consumer-client-id-disaster_mq-x.x.x.x@21171 V4_7_0               1595768036031/consumer-client-id-disaster_mq-x.x.x.x@21171
002  consumer-client-id-disaster_mq-x.x.x.x@19089 V4_7_0               1595768036031/consumer-client-id-disaster_mq-x.x.x.x@19089



字段含义：




名称
含义





-g
消费组名称



-n
NameServer 地址



输出第一列
第几个消费者



输出第二列
clientId



输出第三列
该消费者使用的客户端 RocketMQ 版本



输出第四列
文件路径（filePath），该文件记录了消费者详细信息




消费组进度

通过 consumerProgress 查看该消费组在订阅主题中每个 Queue 消息的消费进度。

命令示例：

$ bin/mqadmin consumerProgress -g pglog_rmq_t_biz_extend_synchbase_consumer -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
#Topic                            #Broker Name                      #QID  #Broker Offset        #Consumer Offset      #Client IP           #Diff                 #LastTime
pglog_rmq_t_biz_extend            disaster_mq_a                     0     17227343              17227343              N/A                  0                     2020-07-26 21:09:30
pglog_rmq_t_biz_extend            disaster_mq_a                     1     16588873              16588873              N/A                  0                     2020-07-26 21:09:30
pglog_rmq_t_biz_extend            disaster_mq_a                     2     12053429              12053429              N/A                  0                     2020-07-26 21:09:35
...
Consume TPS: 3.98
Diff Total: 6



字段含义：




名称
含义





-g
消费组名称



-n
NameServer 地址



Topic
订阅的主题



Broker Name
订阅主题所在的 Broker



QID
订阅主题的 Queue ID



Broker Offset
该 Queue 存储的消息偏移量



Consumer Offset
该 Queue 消费的消息偏移量



Diff
消息堆积情况



LastTime
上次消费消息的时间



Consume TPS
每秒钟消费消息的数量



Diff Total
消息堆积总数




消息回溯

通过 resetOffsetByTime 可以将消费组重新定位到过去某个时间点重新开始消费。

命令示例：

$ bin/mqadmin resetOffsetByTime -n x.x.x.x:9876 -g melon_consumer_0010 -t melon_test_0010 -s now
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
rollback consumer offset by specified group[melon_consumer_0010], topic[melon_test_0010], force[true], timestamp(string)[now], timestamp(long)[1595900214141]
#brokerName                               #queueId                                  #offset
dev_mq_b                                  5                                         281499
dev_mq_b                                  3                                         285922
dev_mq_d                                  5                                         12335
dev_mq_b                                  4                                         286157
dev_mq_b                                  1                                         279566
dev_mq_d                                  3                                         12336
dev_mq_b                                  2                                         281142
dev_mq_d                                  4                                         12333
dev_mq_d                                  1                                         12335
dev_mq_b                                  0                                         282808
dev_mq_d                                  2                                         12338
dev_mq_d                                  0                                         12343



字段含义：




名称
含义





-n
NameServer 地址



-g
消费组名称



-t
消费组定于的主题名称



-s
回溯的时间戳（例如：1595815028792，now 表示当前时间）



brokerName
节点名称



queueId
队列 ID



offset
回溯后该队列消费的偏移量




Broker 命令汇总

Broker 状态

通过 brokerStatus 命令了解集群中某个 Broker 的运行情况，例如：启动时间、版本、吞吐情况等。

命令示例：

$ bin/mqadmin brokerStatus -b x.x.x.x:10911 -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
EndTransactionQueueSize         : 0
EndTransactionThreadPoolQueueCapacity: 100000
bootTimestamp                   : 1591673160936
brokerVersion                   : 353
brokerVersionDesc               : V4_7_0
commitLogDirCapacity            : Total : 98.3 GiB, Free : 93.5 GiB.
commitLogDiskRatio              : 0.04929098258492175
commitLogMaxOffset              : 3473383494
commitLogMinOffset              : 2147483648
consumeQueueDiskRatio           : 0.04929098258492175
dispatchBehindBytes             : 0
dispatchMaxBuffer               : 0
earliestMessageTimeStamp        : 1595621861014
getFoundTps                     : 0.0 0.0 0.0
getMessageEntireTimeMax         : 290
getMissTps                      : 786.5213478652134 783.8549478385495 783.5753864100321
getTotalTps                     : 786.5213478652134 783.8549478385495 783.5753864100321
getTransferedTps                : 0.0 0.0 0.0
msgGetTotalTodayMorning         : 2713099
msgGetTotalTodayNow             : 2713131
msgGetTotalYesterdayMorning     : 1478152
msgPutTotalTodayMorning         : 9303513
msgPutTotalTodayNow             : 9336203
msgPutTotalYesterdayMorning     : 6247199
pageCacheLockTimeMills          : 0
pullThreadPoolQueueCapacity     : 100000
pullThreadPoolQueueHeadWaitTimeMills: 0
pullThreadPoolQueueSize         : 0
putMessageAverageSize           : 326.0440501347282
putMessageDistributeTime        : [<=0ms]:11 [0~10ms]:0 [10~50ms]:0 [50~100ms]:0 [100~200ms]:0 [200~500ms]:0 [500ms~1s]:0 [1~2s]:0 [2~3s]:0 [3~4s]:0 [4~5s]:0 [5~10s]:0 [10s~]:0
putMessageEntireTimeMax         : 930
putMessageSizeTotal             : 3044013439
putMessageTimesTotal            : 9336203
putTps                          : 0.9999000099990001 0.9999000099990001 0.999875015623047
queryThreadPoolQueueCapacity    : 20000
queryThreadPoolQueueHeadWaitTimeMills: 0
queryThreadPoolQueueSize        : 0
remainHowManyDataToCommit       : 0 B
remainHowManyDataToFlush        : 1.1 KiB
remainTransientStoreBufferNumbs : 3
runtime                         : [ 49 days, 21 hours, 38 minutes, 12 seconds ]
scheduleMessageOffset_1         : 2024,2024
scheduleMessageOffset_10        : 1035,1035
scheduleMessageOffset_11        : 885,885
scheduleMessageOffset_12        : 879,879
scheduleMessageOffset_13        : 889,889
scheduleMessageOffset_14        : 640349,640349
scheduleMessageOffset_15        : 848,848
scheduleMessageOffset_16        : 851,851
scheduleMessageOffset_17        : 870,870
scheduleMessageOffset_18        : 1288,1288
scheduleMessageOffset_2         : 1243954,1243954
scheduleMessageOffset_3         : 13682,13682
scheduleMessageOffset_4         : 5965,5965
scheduleMessageOffset_5         : 5134,5134
scheduleMessageOffset_6         : 4741,4741
scheduleMessageOffset_7         : 13475,13475
scheduleMessageOffset_8         : 2530,2530
scheduleMessageOffset_9         : 2270,2270
sendThreadPoolQueueCapacity     : 10000
sendThreadPoolQueueHeadWaitTimeMills: 0
sendThreadPoolQueueSize         : 0
startAcceptSendRequestTimeStamp : 0



字段含义：




名称
含义





-b
Broker 的 IP 地址



-n
Nameserver 地址



EndTransactionQueueSize
END_TRANSACTION 的线程池请求数



EndTransactionThreadPoolQueueCapacity
END_TRANSACTION 线程池大小，默认 100000



bootTimestamp
Broker 启动时间



brokerVersion
Broker 版本



brokerVersionDesc
Broker 版本描述



commitLogDirCapacity
commitLog 目录磁盘使用情况



commitLogDiskRatio
commitLog 目录磁盘使用百分比



commitLogMaxOffset
commitLog 最大偏移量



commitLogMinOffset
commitLog 最小偏移量



dispatchBehindBytes
已在 commit log 中存储未转发到 consume queue 的数据（单位字节）



dispatchMaxBuffer
可忽略未被使用



earliestMessageTimeStamp
存储最早消息的时间戳



getFoundTps
拉取时被找到的消息 Tps 统计，分别表示前 10s、1 分钟、10 分钟平均 Tps



getMessageEntireTimeMax
查找单条消息的最大耗时



getMissTps
拉取时未被找到的消息 Tps 统计，分别表示前 10s、1 分钟、10 分钟平均 Tps



getTotalTps
拉取时总的消息 Tps 统计，分别表示前 10s、1 分钟、10 分钟平均 Tps



getTransferedTps
向拉取方传输消息 Tps 统计，分别表示前 10s、1 分钟、10 分钟平均 Tps



msgGetTotalTodayMorning
截止今天凌晨 12 点从该 broker 拉取的消息总数



msgGetTotalTodayNow
截止当前时间从该 broker 拉取的消息总数



msgGetTotalYesterdayMorning
截止昨天凌晨 12 点从该 broker 拉取的消息总数



msgPutTotalTodayMorning
截止今天凌晨 12 点从该 broker 写入的消息总数



msgPutTotalTodayNow
截止当前时间从该 broker 写入的消息总数



msgPutTotalYesterdayMorning
截止昨天凌晨 12 点从该 broker 写入的消息总数



pageCacheLockTimeMills
消息存储时会加锁，指从加锁现在的时间



pullThreadPoolQueueCapacity
拉取线程池队列初始容量，默认为 100000



pullThreadPoolQueueHeadWaitTimeMills
队列头部第一个任务从创建到现在一直未被执行的时间，即：队列第一个任务等待时间



pullThreadPoolQueueSize
拉取线程池队列当前任务数量



putMessageAverageSize
写入消息的平均大小



putMessageDistributeTime
消息存储的耗时分布情况。例如：[<=0ms]:11 指存储时小于等于 0ms 的有 11 条消息



putMessageEntireTimeMax
消息存储的最大耗时



putMessageSizeTotal
存储消息的总大小



putMessageTimesTotal
存储消息的总条数



putTps
统计 10 秒、1 分钟、10 分钟写入平均 Tps



queryThreadPoolQueueCapacity
查询线程池队列初始容量，默认为 20000



queryThreadPoolQueueHeadWaitTimeMills
队列头部第一个任务从创建到现在一直未被执行的时间，即：队列第一个任务等待时间



queryThreadPoolQueueSize
查询线程池队列当前任务数量



remainHowManyDataToCommit
剩余多少数据未被写入到 fileChannel



remainHowManyDataToFlush
剩余多少数据未被刷到磁盘



remainTransientStoreBufferNumbs
堆外可用缓存区数量，初始大小为 5，每个大小 1G，为在开启队外内存传输时有效



runtime
该 broker 运行了多久了



scheduleMessageOffset_1
SCHEDULE_TOPIC_XXXX 第 1 个 Queue 的最大偏移量 （注：延迟消息存储在名字为 SCHEDULE_TOPIC_XXXX 的 topic 中）



scheduleMessageOffset_10
SCHEDULE_TOPIC_XXXX 第 10 个 Queue 的最大偏移量



scheduleMessageOffset_11
SCHEDULE_TOPIC_XXXX 第 11 个 Queue 的最大偏移量



scheduleMessageOffset_12
SCHEDULE_TOPIC_XXXX 第 12 个 Queue 的最大偏移量



scheduleMessageOffset_13
SCHEDULE_TOPIC_XXXX 第 13 个 Queue 的最大偏移量



scheduleMessageOffset_14
SCHEDULE_TOPIC_XXXX 第 14 个 Queue 的最大偏移量



scheduleMessageOffset_15
SCHEDULE_TOPIC_XXXX 第 15 个 Queue 的最大偏移量



scheduleMessageOffset_16
SCHEDULE_TOPIC_XXXX 第 16 个 Queue 的最大偏移量



scheduleMessageOffset_17
SCHEDULE_TOPIC_XXXX 第 17 个 Queue 的最大偏移量



scheduleMessageOffset_18
SCHEDULE_TOPIC_XXXX 第 18 个 Queue 的最大偏移量



scheduleMessageOffset_2
SCHEDULE_TOPIC_XXXX 第 2 个 Queue 的最大偏移量



scheduleMessageOffset_3
SCHEDULE_TOPIC_XXXX 第 3 个 Queue 的最大偏移量



scheduleMessageOffset_4
SCHEDULE_TOPIC_XXXX 第 4 个 Queue 的最大偏移量



scheduleMessageOffset_5
SCHEDULE_TOPIC_XXXX 第 5 个 Queue 的最大偏移量



scheduleMessageOffset_6
SCHEDULE_TOPIC_XXXX 第 6 个 Queue 的最大偏移量



scheduleMessageOffset_7
SCHEDULE_TOPIC_XXXX 第 7 个 Queue 的最大偏移量



scheduleMessageOffset_8
SCHEDULE_TOPIC_XXXX 第 8 个 Queue 的最大偏移量



scheduleMessageOffset_9
SCHEDULE_TOPIC_XXXX 第 9 个 Queue 的最大偏移量



sendThreadPoolQueueCapacity
发送线程池队列初始容量，默认为 10000



sendThreadPoolQueueHeadWaitTimeMills
队列头部第一个任务从创建到现在一直未被执行的时间，即：队列第一个任务等待时间



sendThreadPoolQueueSize
发送线程池队列当前任务数量



startAcceptSendRequestTimeStamp
可以配置在指定的时间 broker 接受客户端发送请求，默认启动后则接受发送请求




Broker 配置查询

通过 getBrokerConfig 获取 Broker 的配置信息，下面指提供获取命令，具体参数的含义会在另篇中解读。

命令示例：

$ bin/mqadmin getBrokerConfig -b  x.x.x.x:10911 -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
============x.x.x.x:10911============
serverSelectorThreads                             =  3
brokerRole                                        =  ASYNC_MASTER
serverSocketRcvBufSize                            =  131072
osPageCacheBusyTimeOutMills                       =  1000
shortPollingTimeMills                             =  1000
clientSocketRcvBufSize                            =  131072
clusterTopicEnable                                =  true
brokerTopicEnable                                 =  true
autoCreateTopicEnable                             =  true
maxErrorRateOfBloomFilter                         =  20
maxMsgsNumBatch                                   =  64
cleanResourceInterval                             =  10000
...



Broker 配置更新

我们可以通过 updateBrokerConfig 命令对 Broker 配置进行热更新，更新后实时生效，不需要重启 Broker 节点。

命令示例：

$ bin/mqadmin updateBrokerConfig -b x.x.x.x:10911 -n dev-mq1.ttbike.com.cn:9876 -k slaveReadEnable -v true
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
update broker config success, x.x.x.x:10911



字段含义：




名称
含义





-b
Broker 地址



-n
NameServer 地址



-k
需更新的配置的 key



-v
需更新配置 key 对应的值




Broker 发送消息

可以使用 sendMsgStatus 命令对某个 Broker 发送测试消息，检测该 Broker 运行情况。

命令示例：

bin/mqadmin sendMsgStatus -b dev_mq_d -n x.x.x.x:9876
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
rt:2ms, SendResult=SendResult [sendStatus=SEND_OK, msgId=0A6F4B60457D5ACF98009C90AD2C0001, offsetMsgId=0A6F4B6000002AC100000000D0B7A942, messageQueue=MessageQueue [topic=dev_mq_d, brokerName=dev_mq_d, queueId=0], queueOffset=4486548]rt:2ms,...



字段含义：




名称
含义





-b
Broker 名称



-n
NameServer 地址



-c
指定发送消息数量，默认 50 条



-s
指定发送消息体大小，默认 128k




消息命令汇总

打印主题消息

通过命令 printMsg 可以打印主题中的消息。

命令示例：

$ bin/mqadmin printMsg -d true -n x.x.x.x:9876 -t melon_dev_test
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_b, queueId=2]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=4]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_b, queueId=4]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=6]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_b, queueId=6]minOffset=0, maxOffset=1, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=8]MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=8] no matched msg. status=NO_MATCHED_MSG, offset=1minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_b, queueId=8]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=10]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=0]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_b, queueId=0]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=2]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_b, queueId=10]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=12]minOffset=0, maxOffset=0, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_b, queueId=12]minOffset=0, maxOffset=1, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=14]MessageQueue [topic=melon_dev_test, brokerName=dev_mq_d, queueId=14] no matched msg. status=NO_MATCHED_MSG, offset=1minOffset=0, maxOffset=2, MessageQueue [topic=melon_dev_test, brokerName=dev_mq_b, queueId=14]MSGID: 0A6F4BA1743E7BA18F1B9F54E2210028 MessageExt [brokerName=dev_mq_b, queueId=14, storeSize=225, queueOffset=1, sysFlag=0, bornTimestamp=1596205940257, bornHost=/10.111.75.161:42806, storeTimestamp=1596205940257, storeHost=/10.111.75.95:10911, msgId=0A6F4B5F00002A9F000000138873E059, commitLogOffset=83893674073, bodyCRC=1649915861, reconsumeTimes=0, preparedTransactionOffset=0, toString()=Message{topic='melon_dev_test', flag=0, properties={MIN_OFFSET=0, uber-trace-id=7617a5ff2fa5bf68%3A7617a5ff2fa5bf68%3A0%3A0, MAX_OFFSET=2, UNIQ_KEY=0A6F4BA1743E7BA18F1B9F54E2210028, WAIT=true}, body=[104, 101, 108, 108, 111, 32, 98, 97, 98, 121], transactionId='null'}] BODY: hello baby



字段含义：




名称
含义





-d
是否打印消息体，默认 false



-n
NameServer 地址



-t
主题名称



-b
开始时间戳，格式为 currentTimeMillis|yyyy-MM-dd#HH:mm:ss:SSS



-c
字符编码，默认 UTF-8



-e
结束时间戳，格式为 currentTimeMillis|yyyy-MM-dd#HH:mm:ss:SSS



-s
订阅的 tag，默认为全部（*），格式 TagA || TagB




通过 MsgId 检索消息

通过 queryMsgById 命令检索存储在集群中的消息。

命令示例：

$ bin/mqadmin queryMsgById -n x.x.x.x:9876 -i 0A6F4B5F00002A9F000000138873E059
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
OffsetID:            0A6F4B5F00002A9F000000138873E059
Topic:               melon_dev_test
Tags:                [null]
Keys:                [null]
Queue ID:            14
Queue Offset:        1
CommitLog Offset:    83893674073
Reconsume Times:     0
Born Timestamp:      2020-07-31 22:32:20,257
Store Timestamp:     2020-07-31 22:32:20,257
Born Host:           x.x.x.x:42806
Store Host:          x.x.x.x:10911
System Flag:         0
Properties:          {uber-trace-id=7617a5ff2fa5bf68%3A7617a5ff2fa5bf68%3A0%3A0, UNIQ_KEY=0A6F4BA1743E7BA18F1B9F54E2210028, WAIT=true}
Message Body Path:   /tmp/rocketmq/msgbodys/0A6F4BA1743E7BA18F1B9F54E2210028

MessageTrack [consumerGroup=melon_dev_consumer, trackType=NOT_ONLINE, exceptionDesc=CODE:206 DESC:the consumer group[melon_dev_consumer] not online]



字段含义：




名称
含义





-n
NameServer 地址



-i
消息 ID



OffsetID




Topic
主题名称



Tags
消息的 TAG



Keys
发送消息的 key



Queue ID
消息存储的 Queue



Queue Offset
消息在 Queue 中的偏移量



CommitLog Offset
消息在 commitLog 文件中的偏移量



Reconsume Times
重新消费的次数



Born Timestamp
消息诞生的时间戳



Store Timestamp
消息存储的时间戳



Born Host
发送消息的 IP 地址



Store Host
消息存储的 IP 地址



System Flag
标志信息



Properties
属性信息



Message Body Path
消息体存储路径



MessageTrack
消费情况




通过 Key 检索消息

可以通过 queryMsgByKey 命令根据消息 Key 检索消息。

命令示例：

$ bin/mqadmin queryMsgByKey -n x.x.x.x:9876 -t melon_dev_test -k orderNo1

RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
#Message ID                                        #QID                                  #Offset
0A6F4BA1743E7BA18F1B022183DA002B                      2                                        0



字段含义：




名称
含义





-n
NameServer 地址



-t
主题名称



-k
消息 key



Message ID
消息 ID



QID
消息存储的 Queue



Offset
消息在 Queue 的偏移量




根据 Offset 检索消息

消息存储在 Broker 中的 queue 中，同样可以通过 offset 来检索消息。

命令示例：

$ bin/mqadmin queryMsgByOffset -n x.x.x.x:9876 -t melon_dev_test -b dev_mq_b -i 2 -o 0
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
OffsetID:            0A6F4B5F00002A9F00000013A37FED85
Topic:               melon_dev_test
Tags:                [null]
Keys:                [orderNo1]
Queue ID:            2
Queue Offset:        0
CommitLog Offset:    84347448709
Reconsume Times:     0
Born Timestamp:      2020-08-01 09:55:50,874
Store Timestamp:     2020-08-01 09:55:50,875
Born Host:           x.x.x.x:42806
Store Host:          x.x.x.x:10911
System Flag:         0
Properties:          {MIN_OFFSET=0, uber-trace-id=74e72c15f101da93%3A74e72c15f101da93%3A0%3A0, MAX_OFFSET=1, KEYS=orderNo1, UNIQ_KEY=0A6F4BA1743E7BA18F1B022183DA002B, WAIT=true}
Message Body Path:   /tmp/rocketmq/msgbodys/0A6F4BA1743E7BA18F1B022183DA002B



字段含义：




名称
含义





-n
NameServer 地址



-t
主题



-b
Broker 名字



-i
Queue ID



-o
偏移量 offset



OffsetID
消息 ID



Topic
主题名称



Tags
消息的 TAG



Keys
消息 KEY



Queue ID
消息存储的 Queue



Queue Offset
消息在 Queue 中的偏移量



CommitLog Offset
消息在 commitLog 文件中的偏移量



Reconsume Times
重新消费的次数



Born Timestamp
消息诞生的时间戳



Store Timestamp
消息存储的时间戳



Born Host
发送消息的 IP 地址



System Flag
标志信息



Properties
属性信息



Message Body Path
消息体存储路径



MessageTrack
消费情况




根据 UniqueKey 检索消息

通过命令 queryMsgByUniqueKey 同样可以检索消息。

命令示例：

$ bin/mqadmin queryMsgByUniqueKey -n dev-mq1.ttbike.com.cn:9876  -t melon_dev_test -i 0A6F4BA1743E7BA18F1B022183DA002B
RocketMQLog:WARN No appenders could be found for logger (io.netty.util.internal.PlatformDependent0).
RocketMQLog:WARN Please initialize the logger system properly.
Topic:               melon_dev_test
Tags:                [null]
Keys:                [orderNo1]
Queue ID:            2
Queue Offset:        0
CommitLog Offset:    84347448709
Reconsume Times:     0
Born Timestamp:      2020-08-01 09:55:50,874
Store Timestamp:     2020-08-01 09:55:50,875
Born Host:           x.x.x.x:42806
Store Host:          x.x.x.x:10911
System Flag:         0
Properties:          {uber-trace-id=74e72c15f101da93%3A74e72c15f101da93%3A0%3A0, KEYS=orderNo1, UNIQ_KEY=0A6F4BA1743E7BA18F1B022183DA002B, WAIT=true}
Message Body Path:   /tmp/rocketmq/msgbodys/0A6F4BA1743E7BA18F1B022183DA002B



字段含义：

字段含义同上面命令。

                        
                        
                            