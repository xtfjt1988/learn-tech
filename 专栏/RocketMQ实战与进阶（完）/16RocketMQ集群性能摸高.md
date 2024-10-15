
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            16 RocketMQ 集群性能摸高
                            前言

我们在生产环境搭建一个集群时，需要对该集群的性能进行摸高。即：集群的最大 TPS 大约多少，我们做到心里有数。通常我们日常的实际流量控制在压测最高值的 1⁄3 到 1⁄2 左右，预留一倍到两倍的空间应对流量的突增情况。

如何进行压力测试呢？


写段发送代码测试同学通过 JMeter 进行压力测试，或者代码中通过多线程发送消息。这种方式需要多台不错配置的测试机器。
通过 RocketMQ 自带压测脚本。


这两种在实践过程中都使用过，压测效果基本接近，为了方便，建议直接在新搭建的 RocketMQ 集群上直接通过压测脚本进行即可。

压测脚本

在 RocketMQ 安装包解压后，在 benchmark 目录有一个 producer.sh 文件。我们通过该脚本进行压力测试。

下面通过 producer.sh -h 看下各个字段的含义。

字段含义：




名称
含义





-h
使用帮助



-k
测试时消息是否有 key，默认 false



-n
NameServer 地址



-s
消息大小，默认为 128 个字节



-t
主题名称



-w
并发线程的数量，默认 64 个




摸高实战

系统配置 48C256G，集群架构为 4 主 4 从。下面分场景对该集群进行测试，观察输出结果。可以根据实际情况灵活组合，不同的组合结果也不会相同，然而压测的方法是一样的。

测试场景一

1 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 4533，最大 RT 为 299，平均 RT 为 0.22。

sh producer.sh -t cluster-perf-tst8 -w 1 -s 1024 -n x.x.x.x:9876
Send TPS: 4281 Max RT: 299 Average RT:   0.233 Send Failed: 0 Response Failed: 0
Send TPS: 4237 Max RT: 299 Average RT:   0.236 Send Failed: 0 Response Failed: 0
Send TPS: 4533 Max RT: 299 Average RT:   0.221 Send Failed: 0 Response Failed: 0
Send TPS: 4404 Max RT: 299 Average RT:   0.227 Send Failed: 0 Response Failed: 0
Send TPS: 4360 Max RT: 299 Average RT:   0.229 Send Failed: 0 Response Failed: 0
Send TPS: 4269 Max RT: 299 Average RT:   0.234 Send Failed: 0 Response Failed: 0
Send TPS: 4319 Max RT: 299 Average RT:   0.231 Send Failed: 0 Response Failed: 0


测试场景二

1 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 4125，最大 RT 为 255，平均 RT 为 0.24。

sh producer.sh -t cluster-perf-tst8 -w 1 -s 3072 -n 192.168.x.x:9876
Send TPS: 4120 Max RT: 255 Average RT:   0.242 Send Failed: 0 Response Failed: 0
Send TPS: 4054 Max RT: 255 Average RT:   0.246 Send Failed: 0 Response Failed: 0
Send TPS: 4010 Max RT: 255 Average RT:   0.249 Send Failed: 0 Response Failed: 0
Send TPS: 4125 Max RT: 255 Average RT:   0.242 Send Failed: 0 Response Failed: 0
Send TPS: 4093 Max RT: 255 Average RT:   0.244 Send Failed: 0 Response Failed: 0
Send TPS: 4093 Max RT: 255 Average RT:   0.244 Send Failed: 0 Response Failed: 0
Send TPS: 3999 Max RT: 255 Average RT:   0.250 Send Failed: 0 Response Failed: 0
Send TPS: 3957 Max RT: 255 Average RT:   0.253 Send Failed: 0 Response Failed: 0



测试场景三

1 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 5289，最大 RT 为 255，平均 RT 为 0.19。

sh producer.sh -t cluster-perf-tst16 -w 1 -s 1024 -n x.x.x.x:9876
Send TPS: 5289 Max RT: 225 Average RT:   0.189 Send Failed: 0 Response Failed: 0
Send TPS: 5252 Max RT: 225 Average RT:   0.190 Send Failed: 0 Response Failed: 0
Send TPS: 5124 Max RT: 225 Average RT:   0.195 Send Failed: 0 Response Failed: 0
Send TPS: 5146 Max RT: 225 Average RT:   0.194 Send Failed: 0 Response Failed: 0
Send TPS: 4861 Max RT: 225 Average RT:   0.206 Send Failed: 0 Response Failed: 0
Send TPS: 4998 Max RT: 225 Average RT:   0.200 Send Failed: 0 Response Failed: 0
Send TPS: 5063 Max RT: 225 Average RT:   0.198 Send Failed: 0 Response Failed: 0
Send TPS: 5039 Max RT: 225 Average RT:   0.198 Send Failed: 0 Response Failed: 0



测试场景四

1 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 5011，最大 RT 为 244，平均 RT 为 0.21。

sh producer.sh -t cluster-perf-tst16 -w 1 -s 3072 -n x.x.x.x:9876
Send TPS: 4778 Max RT: 244 Average RT:   0.209 Send Failed: 0 Response Failed: 0
Send TPS: 5011 Max RT: 244 Average RT:   0.199 Send Failed: 0 Response Failed: 0
Send TPS: 4826 Max RT: 244 Average RT:   0.207 Send Failed: 0 Response Failed: 0
Send TPS: 4762 Max RT: 244 Average RT:   0.210 Send Failed: 0 Response Failed: 0
Send TPS: 4663 Max RT: 244 Average RT:   0.214 Send Failed: 0 Response Failed: 0
Send TPS: 4648 Max RT: 244 Average RT:   0.215 Send Failed: 0 Response Failed: 0
Send TPS: 4778 Max RT: 244 Average RT:   0.209 Send Failed: 0 Response Failed: 0
Send TPS: 4737 Max RT: 244 Average RT:   0.211 Send Failed: 0 Response Failed: 0
Send TPS: 4523 Max RT: 244 Average RT:   0.221 Send Failed: 0 Response Failed: 0
Send TPS: 4544 Max RT: 244 Average RT:   0.220 Send Failed: 0 Response Failed: 0
Send TPS: 4683 Max RT: 244 Average RT:   0.213 Send Failed: 0 Response Failed: 0
Send TPS: 4838 Max RT: 244 Average RT:   0.207 Send Failed: 0 Response Failed: 0



测试场景五

10 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 41946，最大 RT 为 259，平均 RT 为 0.24。

sh producer.sh -t cluster-perf-tst8 -w 10 -s 1024 -n x.x.x.x:9876
Send TPS: 40274 Max RT: 259 Average RT:   0.248 Send Failed: 0 Response Failed: 0
Send TPS: 41421 Max RT: 259 Average RT:   0.241 Send Failed: 0 Response Failed: 0
Send TPS: 43185 Max RT: 259 Average RT:   0.231 Send Failed: 0 Response Failed: 0
Send TPS: 40654 Max RT: 259 Average RT:   0.246 Send Failed: 0 Response Failed: 0
Send TPS: 40744 Max RT: 259 Average RT:   0.245 Send Failed: 0 Response Failed: 0
Send TPS: 41946 Max RT: 259 Average RT:   0.238 Send Failed: 0 Response Failed: 0



测试场景六

10 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 40927，最大 RT 为 265，平均 RT 为 0.25。

sh producer.sh -t cluster-perf-tst8 -w 10 -s 3072 -n x.x.x.x:9876
Send TPS: 40085 Max RT: 265 Average RT:   0.249 Send Failed: 0 Response Failed: 0
Send TPS: 37710 Max RT: 265 Average RT:   0.265 Send Failed: 0 Response Failed: 0
Send TPS: 39305 Max RT: 265 Average RT:   0.254 Send Failed: 0 Response Failed: 0
Send TPS: 39881 Max RT: 265 Average RT:   0.251 Send Failed: 0 Response Failed: 0
Send TPS: 38428 Max RT: 265 Average RT:   0.260 Send Failed: 0 Response Failed: 0
Send TPS: 39280 Max RT: 265 Average RT:   0.255 Send Failed: 0 Response Failed: 0
Send TPS: 38539 Max RT: 265 Average RT:   0.259 Send Failed: 0 Response Failed: 0
Send TPS: 40927 Max RT: 265 Average RT:   0.244 Send Failed: 0 Response Failed: 0



测试场景七

10 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 42365，最大 RT 为 243，平均 RT 为 0.23。

sh producer.sh -t cluster-perf-tst16 -w 10 -s 1024 -n x.x.x.x:9876
Send TPS: 41301 Max RT: 243 Average RT:   0.242 Send Failed: 0 Response Failed: 0
Send TPS: 42365 Max RT: 243 Average RT:   0.236 Send Failed: 0 Response Failed: 0
Send TPS: 42181 Max RT: 243 Average RT:   0.237 Send Failed: 0 Response Failed: 0
Send TPS: 42261 Max RT: 243 Average RT:   0.237 Send Failed: 0 Response Failed: 0
Send TPS: 40831 Max RT: 243 Average RT:   0.245 Send Failed: 0 Response Failed: 0
Send TPS: 43010 Max RT: 243 Average RT:   0.232 Send Failed: 0 Response Failed: 0
Send TPS: 41871 Max RT: 243 Average RT:   0.239 Send Failed: 0 Response Failed: 0
Send TPS: 40970 Max RT: 243 Average RT:   0.244 Send Failed: 0 Response Failed: 0



测试场景八

10 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 39976，最大 RT 为 237，平均 RT 为 0.25。

sh producer.sh -t cluster-perf-tst16 -w 10 -s 3072 -n x.x.x.x:9876
Send TPS: 36245 Max RT: 237 Average RT:   0.276 Send Failed: 0 Response Failed: 0
Send TPS: 38713 Max RT: 237 Average RT:   0.258 Send Failed: 0 Response Failed: 0
Send TPS: 36327 Max RT: 237 Average RT:   0.275 Send Failed: 0 Response Failed: 0
Send TPS: 39005 Max RT: 237 Average RT:   0.256 Send Failed: 0 Response Failed: 0
Send TPS: 37926 Max RT: 237 Average RT:   0.264 Send Failed: 0 Response Failed: 0
Send TPS: 38804 Max RT: 237 Average RT:   0.258 Send Failed: 0 Response Failed: 0
Send TPS: 39976 Max RT: 237 Average RT:   0.250 Send Failed: 0 Response Failed: 0



测试场景九

30 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 89288，最大 RT 为 309，平均 RT 为 0.34。

sh producer.sh -t cluster-perf-tst8 -w 30 -s 1024 -n x.x.x.x:9876
Send TPS: 86259 Max RT: 309 Average RT:   0.348 Send Failed: 0 Response Failed: 0
Send TPS: 85335 Max RT: 309 Average RT:   0.351 Send Failed: 0 Response Failed: 0
Send TPS: 81850 Max RT: 309 Average RT:   0.366 Send Failed: 0 Response Failed: 0
Send TPS: 87712 Max RT: 309 Average RT:   0.342 Send Failed: 0 Response Failed: 0
Send TPS: 89288 Max RT: 309 Average RT:   0.336 Send Failed: 0 Response Failed: 0
Send TPS: 86732 Max RT: 309 Average RT:   0.346 Send Failed: 0 Response Failed: 0



测试场景十

30 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 77792，最大 RT 为 334，平均 RT 为 0.42。

sh producer.sh -t cluster-perf-tst8 -w 30 -s 3072 -n x.x.x.x:9876
Send TPS: 74085 Max RT: 334 Average RT:   0.405 Send Failed: 0 Response Failed: 0
Send TPS: 71014 Max RT: 334 Average RT:   0.422 Send Failed: 0 Response Failed: 0
Send TPS: 77792 Max RT: 334 Average RT:   0.386 Send Failed: 0 Response Failed: 0
Send TPS: 73913 Max RT: 334 Average RT:   0.406 Send Failed: 0 Response Failed: 0
Send TPS: 77337 Max RT: 334 Average RT:   0.392 Send Failed: 0 Response Failed: 0
Send TPS: 72184 Max RT: 334 Average RT:   0.416 Send Failed: 0 Response Failed: 0
Send TPS: 77271 Max RT: 334 Average RT:   0.388 Send Failed: 0 Response Failed: 0
Send TPS: 75016 Max RT: 334 Average RT:   0.400 Send Failed: 0 Response Failed: 0



测试场景十一

30 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 87009，最大 RT 为 306，平均 RT 为 0.34。

sh producer.sh -t zms-clusterB-perf-tst16 -w 30 -s 1024 -n x.x.x.x:9876
Send TPS: 82946 Max RT: 306 Average RT:   0.362 Send Failed: 0 Response Failed: 0
Send TPS: 86902 Max RT: 306 Average RT:   0.345 Send Failed: 0 Response Failed: 0
Send TPS: 83157 Max RT: 306 Average RT:   0.365 Send Failed: 0 Response Failed: 0
Send TPS: 86804 Max RT: 306 Average RT:   0.345 Send Failed: 0 Response Failed: 0
Send TPS: 87009 Max RT: 306 Average RT:   0.345 Send Failed: 0 Response Failed: 0
Send TPS: 80219 Max RT: 306 Average RT:   0.374 Send Failed: 0 Response Failed: 0



测试场景十二

30 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 78555，最大 RT 为 329，平均 RT 为 0.40。

sh producer.sh -t cluster-perf-tst16 -w 30 -s 3072 -n x.x.x.x:9876
Send TPS: 73864 Max RT: 329 Average RT:   0.403 Send Failed: 0 Response Failed: 0
Send TPS: 78555 Max RT: 329 Average RT:   0.382 Send Failed: 0 Response Failed: 0
Send TPS: 75200 Max RT: 329 Average RT:   0.406 Send Failed: 0 Response Failed: 0
Send TPS: 73925 Max RT: 329 Average RT:   0.406 Send Failed: 0 Response Failed: 0
Send TPS: 69955 Max RT: 329 Average RT:   0.429 Send Failed: 0 Response Failed: 0



测试场景十三

45 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 96340，最大 RT 为 2063，平均 RT 为 0.48。

sh producer.sh -t cluster-perf-tst8 -w 45 -s 1024 -n x.x.x.x:9876
Send TPS: 91266 Max RT: 2063 Average RT:   0.493 Send Failed: 0 Response Failed: 0
Send TPS: 87279 Max RT: 2063 Average RT:   0.515 Send Failed: 0 Response Failed: 0
Send TPS: 92130 Max RT: 2063 Average RT:   0.487 Send Failed: 0 Response Failed: 1
Send TPS: 95227 Max RT: 2063 Average RT:   0.472 Send Failed: 0 Response Failed: 1
Send TPS: 96340 Max RT: 2063 Average RT:   0.467 Send Failed: 0 Response Failed: 1
Send TPS: 84272 Max RT: 2063 Average RT:   0.534 Send Failed: 0 Response Failed: 1



测试场景十四

45 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 90403，最大 RT 为 462，平均 RT 为 0.52。

sh producer.sh -t cluster-perf-tst8 -w 45 -s 3072 -n 192.168.x.x:9876
Send TPS: 89334 Max RT: 462 Average RT:   0.503 Send Failed: 0 Response Failed: 0
Send TPS: 84237 Max RT: 462 Average RT:   0.534 Send Failed: 0 Response Failed: 0
Send TPS: 86051 Max RT: 462 Average RT:   0.523 Send Failed: 0 Response Failed: 0
Send TPS: 86475 Max RT: 462 Average RT:   0.520 Send Failed: 0 Response Failed: 0
Send TPS: 86088 Max RT: 462 Average RT:   0.523 Send Failed: 0 Response Failed: 0
Send TPS: 90403 Max RT: 462 Average RT:   0.498 Send Failed: 0 Response Failed: 0
Send TPS: 84229 Max RT: 462 Average RT:   0.534 Send Failed: 0 Response Failed: 0



测试场景十五

45 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 100158，最大 RT 为 604，平均 RT 为 0.49。

sh producer.sh -t cluster-perf-tst16 -w 45 -s 1024 -n x.x.x.:9876
Send TPS: 91724 Max RT: 604 Average RT:   0.490 Send Failed: 0 Response Failed: 0
Send TPS: 90414 Max RT: 604 Average RT:   0.498 Send Failed: 0 Response Failed: 0
Send TPS: 89904 Max RT: 604 Average RT:   0.500 Send Failed: 0 Response Failed: 0
Send TPS: 100158 Max RT: 604 Average RT:   0.449 Send Failed: 0 Response Failed: 0
Send TPS: 99658 Max RT: 604 Average RT:   0.451 Send Failed: 0 Response Failed: 0
Send TPS: 92440 Max RT: 604 Average RT:   0.489 Send Failed: 0 Response Failed: 0



测试场景十六

45 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 77297，最大 RT 为 436，平均 RT 为 0.39。

sh producer.sh -t cluster-perf-tst16 -w 30 -s 3072 -n x.x.x.x:9876
Send TPS: 75159 Max RT: 436 Average RT:   0.399 Send Failed: 0 Response Failed: 0
Send TPS: 75315 Max RT: 436 Average RT:   0.398 Send Failed: 0 Response Failed: 0
Send TPS: 77297 Max RT: 436 Average RT:   0.388 Send Failed: 0 Response Failed: 0
Send TPS: 72188 Max RT: 436 Average RT:   0.415 Send Failed: 0 Response Failed: 0
Send TPS: 77525 Max RT: 436 Average RT:   0.387 Send Failed: 0 Response Failed: 0
Send TPS: 71535 Max RT: 436 Average RT:   0.422 Send Failed: 0 Response Failed: 0



测试场景十七

60 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 111395，最大 RT 为 369，平均 RT 为 0.53。

sh producer.sh -t cluster-perf-tst8 -w 60 -s 1024 -n x.x.x.x:9876
Send TPS: 110067 Max RT: 369 Average RT:   0.545 Send Failed: 0 Response Failed: 0
Send TPS: 111395 Max RT: 369 Average RT:   0.538 Send Failed: 0 Response Failed: 0
Send TPS: 103114 Max RT: 369 Average RT:   0.582 Send Failed: 0 Response Failed: 0
Send TPS: 107466 Max RT: 369 Average RT:   0.558 Send Failed: 0 Response Failed: 0
Send TPS: 106655 Max RT: 369 Average RT:   0.562 Send Failed: 0 Response Failed: 0
Send TPS: 107241 Max RT: 369 Average RT:   0.559 Send Failed: 0 Response Failed: 1
Send TPS: 110672 Max RT: 369 Average RT:   0.540 Send Failed: 0 Response Failed: 1
Send TPS: 109037 Max RT: 369 Average RT:   0.552 Send Failed: 0 Response Failed: 1



测试场景十八

60 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 99535，最大 RT 为 583，平均 RT 为 0.64。

sh producer.sh -t cluster-perf-tst8 -w 60 -s 3072 -n 192.168.x.x:9876
Send TPS: 92572 Max RT: 583 Average RT:   0.648 Send Failed: 0 Response Failed: 0
Send TPS: 95163 Max RT: 583 Average RT:   0.640 Send Failed: 0 Response Failed: 1
Send TPS: 93823 Max RT: 583 Average RT:   0.654 Send Failed: 0 Response Failed: 1
Send TPS: 97091 Max RT: 583 Average RT:   0.628 Send Failed: 0 Response Failed: 1
Send TPS: 98205 Max RT: 583 Average RT:   0.628 Send Failed: 0 Response Failed: 1
Send TPS: 99535 Max RT: 583 Average RT:   0.596 Send Failed: 0 Response Failed: 3



测试场景十九

60 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 111667，最大 RT 为 358，平均 RT 为 0.55。

sh producer.sh -t cluster-perf-tst16 -w 60 -s 1024 -n x.x.x.x:9876
Send TPS: 105229 Max RT: 358 Average RT:   0.578 Send Failed: 0 Response Failed: 0
Send TPS: 103003 Max RT: 358 Average RT:   0.582 Send Failed: 0 Response Failed: 0
Send TPS: 95497 Max RT: 358 Average RT:   0.628 Send Failed: 0 Response Failed: 0
Send TPS: 108878 Max RT: 358 Average RT:   0.551 Send Failed: 0 Response Failed: 0
Send TPS: 109265 Max RT: 358 Average RT:   0.549 Send Failed: 0 Response Failed: 0
Send TPS: 105545 Max RT: 358 Average RT:   0.568 Send Failed: 0 Response Failed: 0
Send TPS: 111667 Max RT: 358 Average RT:   0.537 Send Failed: 0 Response Failed: 0



测试场景二十

60 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 101073，最大 RT 为 358，平均 RT 为 0.61。

sh producer.sh -t cluster-perf-tst16 -w 60 -s 3072 -n x.x.x.x:9876
Send TPS: 98899 Max RT: 358 Average RT:   0.606 Send Failed: 0 Response Failed: 0
Send TPS: 101073 Max RT: 358 Average RT:   0.594 Send Failed: 0 Response Failed: 0
Send TPS: 97295 Max RT: 358 Average RT:   0.617 Send Failed: 0 Response Failed: 0
Send TPS: 97923 Max RT: 358 Average RT:   0.609 Send Failed: 0 Response Failed: 1
Send TPS: 96111 Max RT: 358 Average RT:   0.620 Send Failed: 0 Response Failed: 2
Send TPS: 93873 Max RT: 358 Average RT:   0.639 Send Failed: 0 Response Failed: 2
Send TPS: 96466 Max RT: 358 Average RT:   0.622 Send Failed: 0 Response Failed: 2
Send TPS: 96579 Max RT: 358 Average RT:   0.621 Send Failed: 0 Response Failed: 2



测试场景二十一

75 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 112707，最大 RT 为 384，平均 RT 为 0.68。

sh producer.sh -t cluster-perf-tst8 -w 75 -s 1024 -n x.x.x.x:9876
Send TPS: 108367 Max RT: 384 Average RT:   0.692 Send Failed: 0 Response Failed: 0
Send TPS: 107516 Max RT: 384 Average RT:   0.701 Send Failed: 0 Response Failed: 0
Send TPS: 110974 Max RT: 384 Average RT:   0.680 Send Failed: 0 Response Failed: 0
Send TPS: 109754 Max RT: 384 Average RT:   0.683 Send Failed: 0 Response Failed: 0
Send TPS: 111917 Max RT: 384 Average RT:   0.670 Send Failed: 0 Response Failed: 0
Send TPS: 104764 Max RT: 384 Average RT:   0.712 Send Failed: 0 Response Failed: 1
Send TPS: 112208 Max RT: 384 Average RT:   0.668 Send Failed: 0 Response Failed: 1
Send TPS: 112707 Max RT: 384 Average RT:   0.665 Send Failed: 0 Response Failed: 1



测试场景二十二

75 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 103953，最大 RT 为 370，平均 RT 为 0.74。

sh producer.sh -t cluster-perf-tst8 -w 75 -s 3072 -n x.x.x.x:9876
Send TPS: 102311 Max RT: 370 Average RT:   0.733 Send Failed: 0 Response Failed: 0
Send TPS: 93722 Max RT: 370 Average RT:   0.800 Send Failed: 0 Response Failed: 0
Send TPS: 101091 Max RT: 370 Average RT:   0.742 Send Failed: 0 Response Failed: 0
Send TPS: 100404 Max RT: 370 Average RT:   0.747 Send Failed: 0 Response Failed: 0
Send TPS: 102328 Max RT: 370 Average RT:   0.733 Send Failed: 0 Response Failed: 0
Send TPS: 103953 Max RT: 370 Average RT:   0.722 Send Failed: 0 Response Failed: 0
Send TPS: 103454 Max RT: 370 Average RT:   0.725 Send Failed: 0 Response Failed: 0



测试场景二十三

75 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 115659，最大 RT 为 605，平均 RT 为 0.68。

sh producer.sh -t cluster-perf-tst16 -w 75 -s 1024 -n x.x.x.x:9876
Send TPS: 106813 Max RT: 605 Average RT:   0.687 Send Failed: 0 Response Failed: 0
Send TPS: 110828 Max RT: 605 Average RT:   0.673 Send Failed: 0 Response Failed: 1
Send TPS: 109855 Max RT: 605 Average RT:   0.676 Send Failed: 0 Response Failed: 3
Send TPS: 102741 Max RT: 605 Average RT:   0.730 Send Failed: 0 Response Failed: 3
Send TPS: 110123 Max RT: 605 Average RT:   0.681 Send Failed: 0 Response Failed: 3
Send TPS: 115659 Max RT: 605 Average RT:   0.648 Send Failed: 0 Response Failed: 3
Send TPS: 108157 Max RT: 605 Average RT:   0.693 Send Failed: 0 Response Failed: 3



测试场景二十四

75 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 99871，最大 RT 为 499，平均 RT 为 0.78。

sh producer.sh -t cluster-perf-tst16 -w 75 -s 3072 -n x.x.x.x:9876
Send TPS: 90459 Max RT: 499 Average RT:   0.829 Send Failed: 0 Response Failed: 0
Send TPS: 96838 Max RT: 499 Average RT:   0.770 Send Failed: 0 Response Failed: 1
Send TPS: 96590 Max RT: 499 Average RT:   0.776 Send Failed: 0 Response Failed: 1
Send TPS: 95137 Max RT: 499 Average RT:   0.788 Send Failed: 0 Response Failed: 1
Send TPS: 89502 Max RT: 499 Average RT:   0.834 Send Failed: 0 Response Failed: 2
Send TPS: 90255 Max RT: 499 Average RT:   0.831 Send Failed: 0 Response Failed: 2
Send TPS: 99871 Max RT: 499 Average RT:   0.725 Send Failed: 0 Response Failed: 9



测试场景二十五

100 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 126590，最大 RT 为 402，平均 RT 为 0.86。

sh producer.sh -t cluster-perf-tst8 -w 100 -s 1024 -n x.x.x.x:9876
Send TPS: 113204 Max RT: 402 Average RT:   0.883 Send Failed: 0 Response Failed: 0
Send TPS: 114872 Max RT: 402 Average RT:   0.868 Send Failed: 0 Response Failed: 1
Send TPS: 116261 Max RT: 402 Average RT:   0.860 Send Failed: 0 Response Failed: 1
Send TPS: 118116 Max RT: 402 Average RT:   0.847 Send Failed: 0 Response Failed: 1
Send TPS: 112594 Max RT: 402 Average RT:   0.888 Send Failed: 0 Response Failed: 1
Send TPS: 124407 Max RT: 402 Average RT:   0.801 Send Failed: 0 Response Failed: 2
Send TPS: 126590 Max RT: 402 Average RT:   0.790 Send Failed: 0 Response Failed: 2



测试场景二十六

100 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 108616，最大 RT 为 426，平均 RT 为 0.93。

sh producer.sh -t cluster-perf-tst8 -w 100 -s 3072 -n x.x.x.x:9876
Send TPS: 106723 Max RT: 426 Average RT:   0.937 Send Failed: 0 Response Failed: 0
Send TPS: 104768 Max RT: 426 Average RT:   0.943 Send Failed: 0 Response Failed: 1
Send TPS: 106697 Max RT: 426 Average RT:   0.935 Send Failed: 0 Response Failed: 2
Send TPS: 105147 Max RT: 426 Average RT:   0.951 Send Failed: 0 Response Failed: 2
Send TPS: 105814 Max RT: 426 Average RT:   0.935 Send Failed: 0 Response Failed: 5
Send TPS: 108616 Max RT: 426 Average RT:   0.916 Send Failed: 0 Response Failed: 6
Send TPS: 101429 Max RT: 426 Average RT:   0.986 Send Failed: 0 Response Failed: 6



测试场景二十七

100 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 123424，最大 RT 为 438，平均 RT 为 0.86。

sh producer.sh -t cluster-perf-tst16 -w 100 -s 1024 -n x.x.x.x:9876
Send TPS: 123424 Max RT: 438 Average RT:   0.805 Send Failed: 0 Response Failed: 0
Send TPS: 111418 Max RT: 438 Average RT:   0.897 Send Failed: 0 Response Failed: 0
Send TPS: 110360 Max RT: 438 Average RT:   0.905 Send Failed: 0 Response Failed: 0
Send TPS: 118734 Max RT: 438 Average RT:   0.842 Send Failed: 0 Response Failed: 0
Send TPS: 120725 Max RT: 438 Average RT:   0.816 Send Failed: 0 Response Failed: 4
Send TPS: 113823 Max RT: 438 Average RT:   0.878 Send Failed: 0 Response Failed: 4
Send TPS: 115639 Max RT: 438 Average RT:   0.865 Send Failed: 0 Response Failed: 4
Send TPS: 112787 Max RT: 438 Average RT:   0.889 Send Failed: 0 Response Failed: 4
Send TPS: 106677 Max RT: 438 Average RT:   0.937 Send Failed: 0 Response Failed: 4
Send TPS: 112635 Max RT: 438 Average RT:   0.888 Send Failed: 0 Response Failed: 4
Send TPS: 108470 Max RT: 438 Average RT:   0.922 Send Failed: 0 Response Failed: 4



测试场景二十八

100 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 103664，最大 RT 为 441，平均 RT 为 0.96。

sh producer.sh -t cluster-perf-tst16 -w 100 -s 3072 -n x.x.x.x:9876
Send TPS: 93374 Max RT: 441 Average RT:   1.071 Send Failed: 0 Response Failed: 3
Send TPS: 98421 Max RT: 441 Average RT:   1.017 Send Failed: 0 Response Failed: 3
Send TPS: 103664 Max RT: 441 Average RT:   0.964 Send Failed: 0 Response Failed: 4
Send TPS: 98234 Max RT: 441 Average RT:   0.995 Send Failed: 0 Response Failed: 6
Send TPS: 103563 Max RT: 441 Average RT:   0.960 Send Failed: 0 Response Failed: 7
Send TPS: 103807 Max RT: 441 Average RT:   0.962 Send Failed: 0 Response Failed: 7
Send TPS: 102715 Max RT: 441 Average RT:   0.973 Send Failed: 0 Response Failed: 7



测试场景二十九

150 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 124567，最大 RT 为 633，平均 RT 为 1.20。

sh producer.sh -t cluster-perf-tst8 -w 150 -s 1024 -n x.x.x.x:9876
Send TPS: 124458 Max RT: 633 Average RT:   1.205 Send Failed: 0 Response Failed: 0
Send TPS: 124567 Max RT: 633 Average RT:   1.204 Send Failed: 0 Response Failed: 0
Send TPS: 121324 Max RT: 633 Average RT:   1.236 Send Failed: 0 Response Failed: 0
Send TPS: 124928 Max RT: 633 Average RT:   1.201 Send Failed: 0 Response Failed: 0
Send TPS: 122830 Max RT: 633 Average RT:   1.242 Send Failed: 0 Response Failed: 0
Send TPS: 118825 Max RT: 633 Average RT:   1.262 Send Failed: 0 Response Failed: 0
Send TPS: 124085 Max RT: 633 Average RT:   1.209 Send Failed: 0 Response Failed: 0



测试场景三十

150 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 107032，最大 RT 为 582，平均 RT 为 1.40。

sh producer.sh -t cluster-perf-tst8 -w 150 -s 3072 -n x.x.x.x:9876
Send TPS: 106575 Max RT: 582 Average RT:   1.404 Send Failed: 0 Response Failed: 1
Send TPS: 101830 Max RT: 582 Average RT:   1.477 Send Failed: 0 Response Failed: 1
Send TPS: 99666 Max RT: 582 Average RT:   1.505 Send Failed: 0 Response Failed: 1
Send TPS: 102139 Max RT: 582 Average RT:   1.465 Send Failed: 0 Response Failed: 2
Send TPS: 105405 Max RT: 582 Average RT:   1.419 Send Failed: 0 Response Failed: 3
Send TPS: 107032 Max RT: 582 Average RT:   1.399 Send Failed: 0 Response Failed: 4
Send TPS: 103416 Max RT: 582 Average RT:   1.448 Send Failed: 0 Response Failed: 5



测试场景三十一

150 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 124474，最大 RT 为 574，平均 RT 为 1.40。

sh producer.sh -t cluster-perf-tst16 -w 150 -s 1024 -n x.x.x.x:9876
Send TPS: 115151 Max RT: 574 Average RT:   1.299 Send Failed: 0 Response Failed: 1
Send TPS: 106960 Max RT: 574 Average RT:   1.402 Send Failed: 0 Response Failed: 1
Send TPS: 116382 Max RT: 574 Average RT:   1.289 Send Failed: 0 Response Failed: 1
Send TPS: 110587 Max RT: 574 Average RT:   1.349 Send Failed: 0 Response Failed: 4
Send TPS: 122832 Max RT: 574 Average RT:   1.220 Send Failed: 0 Response Failed: 4
Send TPS: 124474 Max RT: 574 Average RT:   1.213 Send Failed: 0 Response Failed: 4
Send TPS: 112153 Max RT: 574 Average RT:   1.337 Send Failed: 0 Response Failed: 4
Send TPS: 120450 Max RT: 574 Average RT:   1.261 Send Failed: 0 Response Failed: 4



测试场景三十二

150 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 111285，最大 RT 为 535，平均 RT 为 1.42。

sh producer.sh -t cluster-perf-tst16 -w 150 -s 3072 -n x.x.x.x:9876
Send TPS: 105061 Max RT: 535 Average RT:   1.428 Send Failed: 0 Response Failed: 0
Send TPS: 102117 Max RT: 535 Average RT:   1.465 Send Failed: 0 Response Failed: 1
Send TPS: 105569 Max RT: 535 Average RT:   1.421 Send Failed: 0 Response Failed: 1
Send TPS: 100689 Max RT: 535 Average RT:   1.489 Send Failed: 0 Response Failed: 2
Send TPS: 108464 Max RT: 535 Average RT:   1.381 Send Failed: 0 Response Failed: 2
Send TPS: 111285 Max RT: 535 Average RT:   1.348 Send Failed: 0 Response Failed: 2
Send TPS: 103406 Max RT: 535 Average RT:   1.451 Send Failed: 0 Response Failed: 2
Send TPS: 109203 Max RT: 535 Average RT:   1.388 Send Failed: 0 Response Failed: 2



测试场景三十三

200 个线程、消息大小为 1K、主题为 8 个队列。以下结果中发送最大 TPS 为 126170，最大 RT 为 628，平均 RT 为 1.71。

sh producer.sh -t cluster-perf-tst8 -w 200 -s 1024 -n x.x.x.x:9876
Send TPS: 117965 Max RT: 628 Average RT:   1.674 Send Failed: 0 Response Failed: 7
Send TPS: 115583 Max RT: 628 Average RT:   1.715 Send Failed: 0 Response Failed: 12
Send TPS: 118732 Max RT: 628 Average RT:   1.672 Send Failed: 0 Response Failed: 16
Send TPS: 126170 Max RT: 628 Average RT:   1.582 Send Failed: 0 Response Failed: 17
Send TPS: 116203 Max RT: 628 Average RT:   1.719 Send Failed: 0 Response Failed: 18
Send TPS: 114793 Max RT: 628 Average RT:   1.739 Send Failed: 0 Response Failed: 19



测试场景三十四

200 个线程、消息大小为 3K、主题为 8 个队列。以下结果中发送最大 TPS 为 110892，最大 RT 为 761，平均 RT 为 1.80。

sh producer.sh -t cluster-perf-tst8 -w 200 -s 3072 -n x.x.x.x:9876
Send TPS: 107240 Max RT: 761 Average RT:   1.865 Send Failed: 0 Response Failed: 0
Send TPS: 104585 Max RT: 761 Average RT:   1.906 Send Failed: 0 Response Failed: 2
Send TPS: 110892 Max RT: 761 Average RT:   1.803 Send Failed: 0 Response Failed: 2
Send TPS: 105414 Max RT: 761 Average RT:   1.898 Send Failed: 0 Response Failed: 2
Send TPS: 105904 Max RT: 761 Average RT:   1.885 Send Failed: 0 Response Failed: 3
Send TPS: 110748 Max RT: 761 Average RT:   1.806 Send Failed: 0 Response Failed: 3



测试场景三十五

200 个线程、消息大小为 1K、主题为 16 个队列。以下结果中发送最大 TPS 为 124760，最大 RT 为 601，平均 RT 为 1.63。

sh producer.sh -t cluster-perf-tst16 -w 200 -s 1024 -n x.x.x.x:9876
Send TPS: 118892 Max RT: 601 Average RT:   1.679 Send Failed: 0 Response Failed: 4
Send TPS: 118839 Max RT: 601 Average RT:   1.668 Send Failed: 0 Response Failed: 12
Send TPS: 117122 Max RT: 601 Average RT:   1.704 Send Failed: 0 Response Failed: 12
Send TPS: 122670 Max RT: 601 Average RT:   1.630 Send Failed: 0 Response Failed: 12
Send TPS: 119592 Max RT: 601 Average RT:   1.672 Send Failed: 0 Response Failed: 12
Send TPS: 121243 Max RT: 601 Average RT:   1.649 Send Failed: 0 Response Failed: 12
Send TPS: 124760 Max RT: 601 Average RT:   1.603 Send Failed: 0 Response Failed: 12
Send TPS: 124354 Max RT: 601 Average RT:   1.608 Send Failed: 0 Response Failed: 12
Send TPS: 119272 Max RT: 601 Average RT:   1.677 Send Failed: 0 Response Failed: 12



测试场景三十六

200 个线程、消息大小为 3K、主题为 16 个队列。以下结果中发送最大 TPS 为 111201，最大 RT 为 963，平均 RT 为 1.88。

sh producer.sh -t cluster-perf-tst16 -w 200 -s 3072 -n x.x.x.x:9876
Send TPS: 105091 Max RT: 963 Average RT:   1.896 Send Failed: 0 Response Failed: 4
Send TPS: 106243 Max RT: 963 Average RT:   1.882 Send Failed: 0 Response Failed: 4
Send TPS: 103994 Max RT: 963 Average RT:   1.958 Send Failed: 0 Response Failed: 5
Send TPS: 109741 Max RT: 963 Average RT:   1.822 Send Failed: 0 Response Failed: 5
Send TPS: 103788 Max RT: 963 Average RT:   1.927 Send Failed: 0 Response Failed: 5
Send TPS: 110597 Max RT: 963 Average RT:   1.805 Send Failed: 0 Response Failed: 6
Send TPS: 111201 Max RT: 963 Average RT:   1.798 Send Failed: 0 Response Failed: 6



总结

通过上面的性能压测，可以看出最高 TPS 为 12.6 万。那可以确定集群的理论承载值为 12 万左右，日常流量控制在 4 万左右，当超过 4 万时新申请的主题分配到其他集群。

                        
                        
                            