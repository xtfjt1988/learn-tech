
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            12 搭建基于 Kafka 和 ZooKeeper 的分布式消息队列
                            “纸上得来终觉浅，绝知此事要躬行”，本文将详细介绍基于 Kafka 和 ZooKeeper 的分布式消息队列的搭建方法，并给出 Producer 和 Consumer 代码供读者测试，以便读者对分布式消息队列形成一个整体的认识。在第 12 课中，我将详细介绍基于 Kafka 和 ZooKeeper 的分布式消息队列的原理。

1. ZooKeeper集群搭建

Kafka 将元数据信息保存在 ZooKeeper 中，但发送给 Topic 的数据不会发送到 ZooKeeper 上。Kafka 利用 ZooKeeper 实现动态集群扩展、Leader 选举、负载均衡等功能，因此，我们首先要搭建 ZooKeeper 集群。

1.1 软件环境准备

根据 ZooKeeper 集群的原理，只要超过半数的节点正常，便可提供服务。一般，服务器为奇数台，像 1、3、5……。为什么呢？举个例子，如果服务器为 5 台，则最多可故障两台；如果为 4 台，则最多可故障一台；如果为 3 台，最多也只可故障一台。很明显，偶数台并没有什么意义，4 台服务器相较于 3 台并没有增强可用性。


服务器 IP，本例中我以 3 台服务器为例，生产环境中，为了保证 ZooKeeper 的性能，服务器的内存不小于 4G，以便为 ZooKeeper 提供足够的 Java 堆内存。


server1：192.168.7.100
server2：192.168.7.101
server3：192.168.7.102




Java JDK 1.7及以上：ZooKeeper 基于 Java 编写，运行 ZooKeeper 需要 JRE 环境，点击获得官方下载地址。
ZooKeeper 的稳定版本为 3.4.6，点击获得官方下载地址。


1.2 配置 & 安装 ZooKeeper

以下配置、安装操作，3 台服务器都需要进行。如果读者只有一台服务器，也遵循这个过程，但是，在创建配置、安装目录的时候需要分别命名，端口号也需要加以区别。

（1）安装 JRE 环境

这一步比较简单，这里不详细介绍。需要说明的是，读者可自行安装 JRE，方法有很多，注意版本就行了，需要 1.7 及以上。

yum list java*
yum -y install java-1.7.0-openjdk*



（2）安装 ZooKeeper

首先，在 Linux 系统中建立一个目录用于存放 ZooKeeper 文件，文件命名和目录位置一定要注意规范，以避免不必要的问题。

#我的目录统一放在/opt下面
#首先创建 ZooKeeper 项目目录
mkdir zookeeper  #项目目录
mkdir zkdata     #存放快照日志
mkdir zkdatalog  #存放事物日志



下载 ZooKeeper，写作本文时，最新的版本是 3.4.13，如果服务器无法连接外网，则可在联网的计算机下载后，复制到服务器上。

#下载软件
cd /opt/zookeeper/
wget https://mirrors.cnnic.cn/apache/zookeeper/zookeeper-3.4.13/zookeeper-3.4.13.tar.gz

#解压软件
tar -zxvf zookeeper-3.4.13.tar.gz



（3）创建配置文件

解压目录，之后进入 conf 目录中，查看。

#进入conf目录
/opt/zookeeper/zookeeper-3.4.13/conf

#查看
[[email protected]]$ ll
-rw-rw-r--. 1 1000 1000  535 Feb 20  2014 configuration.xsl
-rw-rw-r--. 1 1000 1000 2161 Feb 20  2014 log4j.properties
-rw-rw-r--. 1 1000 1000  922 Feb 20  2014 zoo_sample.cfg



需要注意： zoo_sample.cfg 文件是官方给我们提供的 ZooKeeper 样板文件，重新复制一份命名为 zoo.cfg。ZooKeeper 启动时将默认加载该路径下名为 zoo.cfg 的配置文件，这是官方指定的文件命名规则。

（4）修改三台服务器的配置文件

使用 vi 命令打开配置文件 zoo.cfg 并进行如下修改：

tickTime=2000
initLimit=10
syncLimit=5
dataDir=/opt/zookeeper/zkdata
dataLogDir=/opt/zookeeper/zkdatalog
clientPort=12181
server.1=192.168.7.100:12888:13888
server.2=192.168.7.101:12888:13888
server.3=192.168.7.102:12888:13888
#server.1 这个1是服务器的标识也可以是其他的数字， 表示这个是第几号服务器，用来标识服务器，这个标识要写到快照目录下面myid文件里
#192.168.7.102为集群里的IP地址，第一个端口是master和slave之间的通信端口，默认是2888，第二个端口是leader选举的端口，集群刚启动的时候选举或者leader挂掉之后进行新的选举的端口默认是3888



下面解释下该配置文件。


tickTime：该时间为 ZooKeeper 服务器之间或客户端与服务器之间维持心跳的时间间隔，也就是每过一个 tickTime 时间就会发送一个心跳；
initLimit：该配置项用来配置客户端（这里所说的客户端不是用户连接 ZooKeeper 服务器的客户端，而是 ZooKeeper 服务器集群中连接到 Leader 的 Follower 服务器）初始化连接时，ZooKeeper 最多能忍受多少个心跳时间间隔。当已经超过 5 个心跳的时间（也就是 tickTime）后，ZooKeeper 服务器还没有收到客户端的返回信息，那么表明这个客户端连接失败。本配置中，总的时间长度不能超过 5 * 2000 = 10 秒；
syncLimit：该配置项用来设置 Leader 与 Follower 之间发送消息、请求和应答的时间长度，最长不能超过多少个 tickTime 时间。本配置中，总的时间长度不能超过 5 * 2000 = 10 秒；
dataDir：快照日志的存储路径；
dataLogDir：事物日志的存储路径，如果不配置该项，事物日志将默认存储到 dataDir 制定的目录中，这样会严重影响 ZooKeeper 的性能。当 ZooKeeper 吞吐量较大时，会产生很多事物日志、快照日志；
clientPort：该端口为客户端连接 ZooKeeper 服务器的端口，ZooKeeper 会监听这个端口，接受客户端的访问请求。


创建 myid 文件，分别在三台服务器上执行如下命令即可。

#server1
echo "1" > /opt/zookeeper/zkdata/myid
#server2
echo "2" > /opt/zookeeper/zkdata/myid
#server3
echo "3" > /opt/zookeeper/zkdata/myid



（5）重要配置说明


myid 为标识本台服务器的文件，存放在快照目录下，是整个 ZooKeeper 集群用来发现彼此的一个重要标识；
zoo.cfg 是 ZooKeeper 配置文件，存放在 conf 目录中；
log4j.properties 是 ZooKeeper 的日志输出文件。conf 目录中用 Java 遍写的程序，它们基本上都有个共同点，即日志都用 log4j 来管理；
zkServer.sh 为主管理程序文件，zkEnv.sh 为主要配置文件，是 ZooKeeper 集群启动时配置环境变量的文件。


此外，还有一点需要注意，我们先看这段英文描述（这一点也可暂时忽略）：


ZooKeeper server will not remove old snapshots and log files when using the default configuration (see autopurge below), this is the responsibility of the operator.


大意是 ZooKeeper 不会主动清除旧的快照和日志文件，应由操作者负责清除。

下面是清除旧快照和日志文件的一些方法。

第一种： 使用 ZooKeeper 工具类 PurgeTxnLog。它实现了一种简单的历史文件清理策略，可以在这里了解它的使用方法。

第二种： 针对上面这个操作，ZooKeeper 已经写好相应的脚本，存放在 bin/zkCleanup.sh 中，所以直接使用该脚本也可以执行清理工作。

第三种： 从 3.4.0 开始，ZooKeeper 提供了自动清理 Snapshot 和事务日志的功能。配置 autopurge.snapRetainCount 和 autopurge.purgeInterval 这两个参数可实现定时清理。这两个参数均在 zoo.cfg 中进行配置：


autopurge.purgeInterval：指定了清理频率，单位是小时，需要填写一个 1 或更大的整数，默认是 0，表示不开启自动清理功能；
autopurge.snapRetainCount：该参数和上面参数搭配使用，它指定了需要保留的文件数目。默认保留 3 个。


推荐自行实现清理快照和文件的方法，对于运维人员来说，将日志清理工作独立出来，便于统一管理，也更可控。毕竟 ZooKeeper 自带的一些工具并不怎么给力。

（6）启动服务并查看

启动服务，代码如下：

#进入到ZooKeeper的bin目录下
cd /opt/zookeeper/zookeeper-3.4.6/bin
#启动服务（3台都需要操作）
./zkServer.sh start



检查服务状态，代码如下：

#检查服务器状态
./zkServer.sh status



通过 status 可看到服务状态：

./zkServer.sh status
JMX enabled by default
Using config: /opt/zookeeper/zookeeper-3.4.6/bin/../conf/zoo.cfg  #配置文件
Mode: follower  #本节点的角色是follower



正常情况下，ZooKeeper 集群只有一个 Leader，多个 Follower。Leader 负责处理客户端的读写请求，而 Follower 则仅同步 Leader 数据。当 Leader 挂掉之后，Follower 会发起投票选举，最终选出一个新的 Leader 。可以用 Jps 命令查看 ZooKeeper 的进程，该进程是 ZooKeeper 整个工程的 main 函数。

#执行命令jps
20348 Jps
4233 QuorumPeerMain 



2. Kafka 集群搭建

2.1 软件环境准备

首先，需要一台以上 Linux 服务器。这里，我们同样使用三台服务器搭建 ZooKeeper 集群。

另外，环境中需提前搭建好 ZooKeeper 集群。Kafka 安装包，我们选择最新版。写作本文时，最新版本为 Kafka_2.11-1.0.2.tgz。

2.2 Kafka 下载 & 安装

下载及安装，代码如下：

#创建目录
cd /opt/
mkdir kafka #创建项目目录
cd kafka
mkdir kafkalogs #创建kafka消息目录，主要存放kafka消息

#下载软件
wget  http://apache.opencas.org/kafka/1.0.2/kafka_2.11-1.0.2.tgz

#解压软件
tar -zxvf kafka_2.11-1.0.2.tgz



2.3 修改配置文件

进入到 config 目录：

cd /opt/kafka/kafka_2.11-1.0.2.tgz/config/



这里主要关注下 server.properties 文件即可。在目录下有很多文件，其中包括 ZooKeeper 文件，我们可以根据 Kafka 内带的 ZooKeeper 集群来启动，但建议使用独立的 ZooKeeper 集群。

-rw-r--r--. 1 root root 5699 Feb 22 09:41 192.168.7.101
-rw-r--r--. 1 root root  906 Feb 12 08:37 connect-console-sink.properties
-rw-r--r--. 1 root root  909 Feb 12 08:37 connect-console-source.properties
-rw-r--r--. 1 root root 2110 Feb 12 08:37 connect-distributed.properties
-rw-r--r--. 1 root root  922 Feb 12 08:38 connect-file-sink.properties
-rw-r--r--. 1 root root  920 Feb 12 08:38 connect-file-source.properties
-rw-r--r--. 1 root root 1074 Feb 12 08:37 connect-log4j.properties
-rw-r--r--. 1 root root 2055 Feb 12 08:37 connect-standalone.properties
-rw-r--r--. 1 root root 1199 Feb 12 08:37 consumer.properties
-rw-r--r--. 1 root root 4369 Feb 12 08:37 log4j.properties
-rw-r--r--. 1 root root 2228 Feb 12 08:38 producer.properties
-rw-r--r--. 1 root root 5699 Feb 15 18:10 server.properties
-rw-r--r--. 1 root root 3325 Feb 12 08:37 test-log4j.properties
-rw-r--r--. 1 root root 1032 Feb 12 08:37 tools-log4j.properties
-rw-r--r--. 1 root root 1023 Feb 12 08:37 zookeeper.properties



修改配置文件 server.properties，如下：

broker.id=0  #当前机器在集群中的唯一标识，和ZooKeeper的myid性质一样
port=19092 #当前kafka对外提供服务的端口默认是9092
host.name=192.168.7.100 #这个参数默认是关闭的，在0.8.1有个bug，DNS解析问题，失败率的问题。
num.network.threads=3 #这个是borker进行网络处理的线程数
num.io.threads=8 #这个是borker进行I/O处理的线程数
log.dirs=/opt/kafka/kafkalogs/ #消息存放的目录，这个目录可以配置为“，”逗号分割的表达式，上面的num.io.threads要大于这个目录的个数这个目录，如果配置多个目录，新创建的topic他把消息持久化的地方是，当前以逗号分割的目录中，那个分区数最少就放那一个
socket.send.buffer.bytes=102400 #发送缓冲区buffer大小，数据不是一下子就发送的，先回存储到缓冲区了到达一定的大小后在发送，能提高性能
socket.receive.buffer.bytes=102400 #kafka接收缓冲区大小，当数据到达一定大小后在序列化到磁盘
socket.request.max.bytes=104857600 #这个参数是向kafka请求消息或者向kafka发送消息的请请求的最大数，这个值不能超过java的堆栈大小
num.partitions=1 #默认的分区数，一个topic默认1个分区数
log.retention.hours=168 #默认消息的最大持久化时间，168小时，7天
message.max.byte=5242880  #消息保存的最大值5M
default.replication.factor=2  #kafka保存消息的副本数，如果一个副本失效了，另一个还可以继续提供服务
replica.fetch.max.bytes=5242880  #取消息的最大直接数
log.segment.bytes=1073741824 #这个参数是：因为kafka的消息是以追加的形式落地到文件，当超过这个值的时候，kafka会新起一个文件
log.retention.check.interval.ms=300000 #每隔300000毫秒去检查上面配置的log失效时间（log.retention.hours=168 ），到目录查看是否有过期的消息如果有，删除
log.cleaner.enable=false #是否启用log压缩，一般不用启用，启用的话可以提高性能
zookeeper.connect=192.168.7.100:12181,192.168.7.101:12181,192.168.7.102:1218 #设置ZooKeeper的连接端口



代码中已给出了参数的解释，以下是实际要修改的参数：

#broker.id=0  每台服务器的broker.id都不能相同

#hostname
host.name=192.168.7.100

#在log.retention.hours=168 下面新增下面三项
message.max.byte=5242880
default.replication.factor=2
replica.fetch.max.bytes=5242880

#设置ZooKeeper的连接端口
zookeeper.connect=192.168.7.100:12181,192.168.7.101:12181,192.168.7.102:12181



2.4 启动 Kafka 集群并测试

（1）启动服务

#从后台启动Kafka集群（3 台都需要启动）
cd /opt/kafka/kafka_2.11-1.0.2/bin #进入到kafka的bin目录 
./kafka-server-start.sh ../config/server.properties &



（2）检查服务是否启动

#执行命令jps
20348 Jps
4233 QuorumPeerMain
18991 Kafka



（3）创建 Topic 来验证是否创建成功

#创建Topic
./kafka-topics.sh --create --zookeeper 192.168.7.100:12181 --replication-factor 2 --partitions 1 --topic mytopic
#解释
--replication-factor 2   #副本数为2
--partitions 1           #创建1个分区
--topic mytopic          #主题名为mytopic

'''在一台服务器上创建一个发布者'''
#创建一个broker，发布者
./kafka-console-producer.sh --broker-list 192.168.7.100:19092 --topic mytopic

'''在另一台服务器上创建一个订阅者'''
./kafka-console-consumer.sh --zookeeper 192.168.7.101:12181 --topic mytopic --from-beginning



了解更多请看官方文档。

（4）测试

读者自行测试，测试方法为在发布者窗口里发布消息，观察订阅者是否能正常收到。

（5）其它命令

大部分命令，我们可以去官方文档查看，这里仅列举以下两个例子。

例子 1：查看 Topic。

./kafka-topics.sh --list --zookeeper localhost:12181
#执行命令将显示我们创建的所有topic



例子 2：查看 Topic 状态。

/kafka-topics.sh --describe --zookeeper localhost:12181 --topic mytopic
#执行命令将显示topic状态信息



3. Java 客户端测试

（1）建立 Maven 工程，添加依赖：

    <dependency>
      <groupId>org.apache.kafka</groupId>
      <artifactId>kafka_2.12</artifactId>
      <version>2.0.0</version>
    </dependency>
    <dependency>
      <groupId>org.apache.kafka</groupId>
      <artifactId>kafka-clients</artifactId>
      <version>2.0.0</version>
    </dependency>



（2）创建 Topic。先用命令行创建一个用于测试的 Topic，我将它命名为 mytopic。

#创建Topic
./kafka-topics.sh --create --zookeeper 192.168.7.100:12181 --replication-factor 2 --partitions 1 --topic mytopic
#解释
--replication-factor 2   #复本数量为2，提高可用性
--partitions 1           #创建1个分区
--topic mytopic          #主题名为mytopic



（3）创建 Producer：

import org.apache.kafka.clients.admin.AdminClient;
import org.apache.kafka.clients.admin.NewTopic;
import org.apache.kafka.clients.producer.Callback;
import org.apache.kafka.clients.producer.KafkaProducer;
import org.apache.kafka.clients.producer.ProducerRecord;
import org.apache.kafka.clients.producer.RecordMetadata;
import org.apache.kafka.common.config.TopicConfig;

import kafka.admin.TopicCommand;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collection;
import java.util.HashMap;
import java.util.Map;
import java.util.Properties;

public class Producer
{

    public static void main(String[] args)
    {
        // 初始化配置
        Properties configs = initConfig();
        // 创建生产者
        KafkaProducer<String, String> producer = new KafkaProducer<String, String>(configs);
        // 向指定topic发送消息
        Map<String, String> topicMsg = new HashMap<String, String>();
        topicMsg.put("mytopic", "send message to kafka from producer");
        sendMessage(topicMsg, producer);

        // 关闭生产者
        producer.close();
    }
    /**
     * 发送消息到指定的topic
     * 
     * @param topicMsg
     * @param producer
     */
    private static void sendMessage(Map<String, String> topicMsg, KafkaProducer<String, String> producer)
    {

        for (Map.Entry<String, String> entry : topicMsg.entrySet()) 
        {
            String topic = entry.getKey();
            String message = entry.getValue();
            ProducerRecord<String, String> record = new ProducerRecord<String, String>(topic, message);
            // 发送
            producer.send(record, new Callback() 
            {
                public void onCompletion(RecordMetadata recordMetadata, Exception e) 
                {
                    if (null != e)
                    {
                        System.out.println("send error" + e.getMessage());
                    }
                    else 
                    {
                        System.out.println(String.format("offset:%s,partition:%s",recordMetadata.offset(),recordMetadata.partition()));
                    }
                }
            });

        }

        producer.flush();
    }

    /**
     * 初始化配置
     */
    private static Properties initConfig()
    {
        Properties props = new Properties();
        props.put("bootstrap.servers", "192.168.7.100:19092,192.168.7.101:19092,192.168.7.102:19092");
        props.put("key.serializer", "org.apache.kafka.common.serialization.StringSerializer");
        props.put("value.serializer", "org.apache.kafka.common.serialization.StringSerializer");
        props.put("retries", "3");
        props.put("acks", "1");
        return props;
    }

}



三次的运行结果如下：

#第一次运行
offset:0,partition:0
#第二次运行
offset:1,partition:0
#第三次运行
offset:2,partition:0



（4）创建 Consumer：

import java.time.Duration;
import java.util.ArrayList;
import java.util.List;
import java.util.Properties;

import org.apache.kafka.clients.consumer.ConsumerRecord;
import org.apache.kafka.clients.consumer.ConsumerRecords;
import org.apache.kafka.clients.consumer.KafkaConsumer;

public class Consumer
{

    public static void main(String[] args)
    {
        Properties configs = initConfig();
        KafkaConsumer<String, String> consumer = new KafkaConsumer<String, String>(configs);

        List<String> topics = new ArrayList<>();
        topics.add("mytopic");
        consumer.subscribe(topics);
        while (true) 
        {
            ConsumerRecords<String, String> records = consumer.poll(Duration.ofSeconds(10));
            for (ConsumerRecord<String, String> record : records)
            {
                System.err.printf("offset = %d, key = %s, value = %s%n", record.offset(), record.key(), record.value());
            }
        }
    }

    /**
     * 初始化配置
     */
    private static Properties initConfig()
    {
        Properties properties = new Properties();
        properties.put("bootstrap.servers","192.168.7.100:19092,192.168.7.101:19092,192.168.7.102:19092");
        properties.put("group.id","0");
        properties.put("key.deserializer", "org.apache.kafka.common.serialization.StringDeserializer");
        properties.put("value.deserializer", "org.apache.kafka.common.serialization.StringDeserializer");
        properties.setProperty("enable.auto.commit", "true");
        properties.setProperty("auto.offset.reset", "earliest");
        return properties;
    }

}



运行结果如下：

offset = 0, key = null, value = send message to kafka from producer
offset = 1, key = null, value = send message to kafka from producer
offset = 2, key = null, value = send message to kafka from producer



参考文献


Kafka 官方文档
ZooKeeper 官方文档


                        
                        
                            