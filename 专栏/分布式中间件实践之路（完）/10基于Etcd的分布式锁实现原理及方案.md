
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            10 基于 Etcd 的分布式锁实现原理及方案
                            Etcd 最新版本已经提供了支持分布式锁的基础接口（可见官网说明），但本文并不局限于此。

本文将介绍两条实现分布式锁的技术路线：


从分布式锁的原理出发，结合 Etcd 的特性，洞见分布式锁的实现细节；
基于 Etcd 提供的分布式锁基础接口进行封装，实现分布式锁。


两条路线差距甚远，建议读者先看路线 1，以便了解 Etcd 实现分布式锁的细节。

1. 为什么选择 Etcd

据官网介绍，Etcd 是一个分布式，可靠的 Key-Value 存储系统，主要用于存储分布式系统中的关键数据。初见之下，Etcd 与 NoSQL 数据库系统有几分相似，但作为数据库绝非 Etcd 所长，其读写性能远不如 MongoDB、Redis 等 Key-Value 存储系统。“让专业的人做专业的事！” Ectd 作为一个高可用的键值存储系统，有很多典型的应用场景，本文将介绍 Etcd 的优秀实践之一：分布式锁。

1.1 Etcd 优点

目前，可实现分布式锁的开源软件有很多，其中应用最广泛、大家最熟悉的应该就是 ZooKeeper，此外还有数据库、Redis、Chubby 等。但若从读写性能、可靠性、可用性、安全性和复杂度等方面综合考量，作为后起之秀的 Etcd 无疑是其中的 “佼佼者” 。它完全媲美业界“名宿”ZooKeeper，在有些方面，Etcd 甚至超越了 ZooKeeper，如 Etcd 采用的 Raft 协议就要比 ZooKeeper 采用的 Zab 协议简单、易理解。

Etcd 作为 CoreOS 开源项目，有以下的特点。


简单：使用 Go 语言编写，部署简单；支持 cURL 方式的用户 API （HTTP+JSON），使用简单；开源 Java 客户端使用简单；
安全：可选 SSL 证书认证；
快速：在保证强一致性的同时，读写性能优秀，详情可查看官方提供的 Benchmark 数据 ；
可靠：采用 Raft 算法实现分布式系统数据的高可用性和强一致性。


1.2 分布式锁的基本原理

分布式环境下，多台机器上多个进程对同一个共享资源（数据、文件等）进行操作，如果不做互斥，就有可能出现“余额扣成负数”，或者“商品超卖”的情况。为了解决这个问题，需要分布式锁服务。首先，来看一下分布式锁应该具备哪些条件。


互斥性：在任意时刻，对于同一个锁，只有一个客户端能持有，从而保证一个共享资源同一时间只能被一个客户端操作；
安全性：即不会形成死锁，当一个客户端在持有锁的期间崩溃而没有主动解锁的情况下，其持有的锁也能够被正确释放，并保证后续其它客户端能加锁；
可用性：当提供锁服务的节点发生宕机等不可恢复性故障时，“热备” 节点能够接替故障的节点继续提供服务，并保证自身持有的数据与故障节点一致。
对称性：对于任意一个锁，其加锁和解锁必须是同一个客户端，即客户端 A 不能把客户端 B 加的锁给解了。


1.3 Etcd 实现分布式锁的基础

Etcd 的高可用性、强一致性不必多说，前面章节中已经阐明，本节主要介绍 Etcd 支持的以下机制：Watch 机制、Lease 机制、Revision 机制和 Prefix 机制，正是这些机制赋予了 Etcd 实现分布式锁的能力。


Lease 机制：即租约机制（TTL，Time To Live），Etcd 可以为存储的 Key-Value 对设置租约，当租约到期，Key-Value 将失效删除；同时也支持续约，通过客户端可以在租约到期之前续约，以避免 Key-Value 对过期失效。Lease 机制可以保证分布式锁的安全性，为锁对应的 Key 配置租约，即使锁的持有者因故障而不能主动释放锁，锁也会因租约到期而自动释放。
Revision 机制：每个 Key 带有一个 Revision 号，每进行一次事务便加一，因此它是全局唯一的，如初始值为 0，进行一次 put(key, value)，Key 的 Revision 变为 1，同样的操作，再进行一次，Revision 变为 2；换成 key1 进行 put(key1, value) 操作，Revision 将变为 3；这种机制有一个作用：通过 Revision 的大小就可以知道写操作的顺序。在实现分布式锁时，多个客户端同时抢锁，根据 Revision 号大小依次获得锁，可以避免 “羊群效应” （也称“惊群效应”），实现公平锁。
Prefix 机制：即前缀机制，也称目录机制，例如，一个名为 /mylock 的锁，两个争抢它的客户端进行写操作，实际写入的 Key 分别为：key1="/mylock/UUID1",key2="/mylock/UUID2"，其中，UUID 表示全局唯一的 ID，确保两个 Key 的唯一性。很显然，写操作都会成功，但返回的 Revision 不一样，那么，如何判断谁获得了锁呢？通过前缀“/mylock” 查询，返回包含两个 Key-Value 对的 Key-Value 列表，同时也包含它们的 Revision，通过 Revision 大小，客户端可以判断自己是否获得锁，如果抢锁失败，则等待锁释放（对应的 Key 被删除或者租约过期），然后再判断自己是否可以获得锁。
Watch 机制：即监听机制，Watch 机制支持监听某个固定的 Key，也支持监听一个范围（前缀机制），当被监听的 Key 或范围发生变化，客户端将收到通知；在实现分布式锁时，如果抢锁失败，可通过 Prefix 机制返回的 Key-Value 列表获得 Revision 比自己小且相差最小的 Key（称为 Pre-Key），对 Pre-Key 进行监听，因为只有它释放锁，自己才能获得锁，如果监听到 Pre-Key 的 DELETE 事件，则说明 Pre-Key 已经释放，自己已经持有锁。


2. Etcd Java 客户端 jetcd

jetcd 是 Etcd 的 Java 客户端，为 Etcd 的特性提供了丰富的接口，使用起来非常方便。不过，需要注意的是，jetcd 支持 Etcd V3 版本（Etcd 较早的版本是 V2），运行环境需 Java 1.8 及以上。


Git 地址：https://github.com/etcd-io/jetcd


2.1 jetcd 基本用法

首先创建一个 Maven 工程，导入 jetcd 依赖。目前，jetcd 最新的版本为：0.0.2

<dependency>
  <groupId>io.etcd</groupId>
  <artifactId>jetcd-core</artifactId>
  <version>${jetcd-version}</version>
</dependency>



（1）Key-Value 客户端

Etcd 作为一个 Key-Value 存储系统，Key-Value 客户端是最基本的客户端，进行 Put、Get、Delete 操作。

// 创建客户端，本例中Etcd服务端为单机模式
Client client = Client.builder().endpoints("http://localhost:2379").build();
KV kvClient = client.getKVClient();
// 对String类型的Key-Value进行类型转换
ByteSequence key = ByteSequence.fromString("test_key");
ByteSequence value = ByteSequence.fromString("test_value");

// put操作，等待操作完成
kvClient.put(key, value).get();

// get操作，等待操作完成
CompletableFuture<GetResponse> getFuture = kvClient.get(key);
GetResponse response = getFuture.get();

// delete操作，等待操作完成
kvClient.delete(key).get();



（2）Lease 客户端

Lease 客户端，即租约客户端，用于创建租约、续约、解约，以及检索租约的详情，如租约绑定的键值等信息。

// 创建客户端，本例中Etcd服务端为单机模式
Client client = Client.builder().endpoints("http://localhost:2379").build();

 // 创建Lease客户端
Lease leaseClient = client.getLeaseClient();

// 创建一个60s的租约，等待完成，超时设置阈值30s
Long leaseId = leaseClient.grant(60).get(30, TimeUnit.SECONDS).getID();

// 使指定的租约永久有效，即永久租约
leaseClient.keepAlive(leaseId);

// 续约一次
leaseClient.keepAliveOnce(leaseId);

// 解除租约，绑定该租约的键值将被删除
leaseClient.revoke(leaseId);

// 检索指定ID对应的租约的详细信息
LeaseTimeToLiveResponse lTRes = leaseClient.timeToLive(leaseId, LeaseOption.newBuilder().withAttachedKeys().build()).get(); 



（3）Watch 客户端

监听客户端，可为 Key 或者目录（前缀机制）创建 Watcher，Watcher 可以监听 Key 的事件（Put、Delete 等），如果事件发生，可以通知客户端，客户端采取某些措施。

// 创建客户端，本例中Etcd服务端为单机模式
Client client = Client.builder().endpoints("http://localhost:2379").build();

// 对String类型的Key进行类型转换
ByteSequence key = ByteSequence.fromString("test_key");

// 创建Watch客户端
Watch watchClient = client.getWatchClient();
// 为Key创建一个Watcher
Watcher watcher = watch.watch(key);

// 开始listen，如果监听的Key有事件(如删除、更新等)发生则返回
WatchResponse response = null;
try
{
    response = watcher.listen();
}
catch (InterruptedException e)
{
    System.out.println("Failed to listen key:"+e);
}
if(response != null)
{
    List<WatchEvent> eventlist = res.getEvents();
    // 解析eventlist，判断是否为自己关注的事件，作进一步操作
    // To do something
}   



（4）Cluster 客户端

为了保障高可用性，实际应用中 Etcd 应工作于集群模式下，集群节点数量为大于 3 的奇数，为了灵活的管理集群，jetcd 提供了集群管理客户端，支持获取集群成员列表、增加成员、删除成员等操作。

// 创建客户端，本例中Etcd服务端为单机模式
Client client = Client.builder().endpoints("http://localhost:2379").build();

// 创建Cluster客户端
Cluster clusterClient = client.getClusterClient();

// 获取集群成员列表
List<Member> list = clusterClient.listMember().get().getMembers();

// 向集群中添加成员
String tempAddr = "http://localhost:2389";
List<String> peerAddrs = new ArrayList<String>();
peerAddrs.add(tempAddr); 
clusterClient.addMember(peerAddrs);

// 根据成员ID删除成员
long memberID = 8170086329776576179L;
clusterClient.removeMember(memberID);

// 更新
clusterClient.updateMember(memberID, peerAddrs);



（5）Maintenance 客户端

Etcd 本质上是一个 Key-Value 存储系统，在一系列的 Put、Get、Delete 及 Compact 操作后，集群节点可能出现键空间不足等告警，通过 Maintenance 客户端，可以进行告警查询、告警解除、整理压缩碎片等操作。

// 创建客户端，本例中Etcd服务端为单机模式
Client client = Client.builder().endpoints("http://localhost:2379").build();

// 创建一个Maintenance 客户端
Maintenance maintClient = client.getMaintenanceClient();

// 获取指定节点的状态,对res做进一步解析可得节点状态详情
StatusResponse res = maintClient.statusMember("http://localhost:2379").get();

// 对指定的节点进行碎片整理，在压缩键空间之后，后端数据库可能呈现内部碎片，需进行整理
// 整理碎片是一个“昂贵”的操作，应避免同时对多个节点进行整理
maintClient.defragmentMember("http://localhost:2379").get();

// 获取所有活跃状态的键空间告警
List<AlarmMember> alarmList = maintClient.listAlarms().get().getAlarms();

// 解除指定键空间的告警
maintClient.alarmDisarm(alarmList.get(0));



3. Etcd 实现分布式锁：路线一

通过前面章节的铺垫，对于如何用 Etcd 实现分布式锁，相信读者已经心中有数，理解了原理，实现反而是简单的。在此，我给出一个 Demo 供读者参考。

3.1 基于 Etcd 的分布式锁业务流程

下面描述了使用 Etcd 实现分布式锁的业务流程，假设对某个共享资源设置的锁名为：/lock/mylock。

步骤1：准备

客户端连接 Etcd，以 /lock/mylock 为前缀创建全局唯一的 Key，假设第一个客户端对应的 Key="/lock/mylock/UUID1"，第二个为 Key="/lock/mylock/UUID2"；客户端分别为自己的 Key 创建租约 Lease，租约的长度根据业务耗时确定，假设为 15s。

步骤2：创建定时任务作为租约的“心跳”

在一个客户端持有锁期间，其它客户端只能等待，为了避免等待期间租约失效，客户端需创建一个定时任务作为“心跳”进行续约。此外，如果持有锁期间客户端崩溃，心跳停止，Key 将因租约到期而被删除，从而锁释放，避免死锁。

步骤3：客户端将自己全局唯一的 Key 写入 Etcd

进行 Put 操作，将步骤 1 中创建的 Key 绑定租约写入 Etcd，根据 Etcd 的 Revision 机制，假设两个客户端 Put 操作返回的 Revision 分别为1、2，客户端需记录 Revision 用以接下来判断自己是否获得锁。

步骤4：客户端判断是否获得锁

客户端以前缀 /lock/mylock 读取 Key-Value 列表（Key-Value 中带有 Key 对应的 Revision），判断自己 Key 的 Revision 是否为当前列表中最小的，如果是则认为获得锁；否则监听列表中前一个 Revision 比自己小的 Key 的删除事件，一旦监听到删除事件或者因租约失效而删除的事件，则自己获得锁。

步骤5：执行业务

获得锁后，操作共享资源，执行业务代码。

步骤6：释放锁

完成业务流程后，删除对应的 Key 释放锁。

3.2 基于 Etcd 的分布式锁的原理图

根据上一节中介绍的业务流程，基于Etcd的分布式锁示意图如下。



业务流程图大家可参看这篇文章《Zookeeper 分布式锁实现原理》。

3.3 基于 Etcd 实现分布式锁的客户端 Demo

Demo 代码如下：

import java.util.List;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.TimeoutException;

import com.coreos.jetcd.Client;
import com.coreos.jetcd.KV;
import com.coreos.jetcd.Lease;
import com.coreos.jetcd.Watch.Watcher;
import com.coreos.jetcd.options.GetOption;
import com.coreos.jetcd.options.GetOption.SortTarget;
import com.coreos.jetcd.options.PutOption;
import com.coreos.jetcd.watch.WatchEvent;
import com.coreos.jetcd.watch.WatchResponse;
import com.coreos.jetcd.data.ByteSequence;
import com.coreos.jetcd.data.KeyValue;
import com.coreos.jetcd.kv.PutResponse;

import java.util.UUID;

/**
 * Etcd 客户端代码，用多个线程“抢锁”模拟分布式系统中，多个进程“抢锁”
 *
 */
public class EtcdClient
{

    public static void main(String[] args) throws InterruptedException, ExecutionException,
            TimeoutException, ClassNotFoundException
    {
            // 创建Etcd客户端，Etcd服务端为单机模式
        Client client = Client.builder().endpoints("http://localhost:2379").build();

        // 对于某共享资源制定的锁名
        String lockName = "/lock/mylock";

        // 模拟分布式场景下，多个进程“抢锁”
        for (int i = 0; i < 3; i++)
        {
            new MyThread(lockName, client).start();
        }    
    }

    /**
     * 加锁方法，返回值为加锁操作中实际存储于Etcd中的Key，即：lockName+UUID，
     * 根据返回的Key，可删除存储于Etcd中的键值对，达到释放锁的目的。
     * 
     * @param lockName
     * @param client
     * @param leaseId
     * @return
     */
    public static String lock(String lockName, Client client, long leaseId)
    {
        // lockName作为实际存储在Etcd的中的Key的前缀，后缀是一个全局唯一的ID，从而确保：对于同一个锁，不同进程存储的Key具有相同的前缀，不同的后缀
        StringBuffer strBufOfRealKey = new StringBuffer();
        strBufOfRealKey.append(lockName);
        strBufOfRealKey.append("/");
        strBufOfRealKey.append(UUID.randomUUID().toString());

        // 加锁操作实际上是一个put操作，每一次put操作都会使revision增加1，因此，对于任何一次操作，这都是唯一的。(get,delete也一样)
        // 可以通过revision的大小确定进行抢锁操作的时序，先进行抢锁的，revision较小，后面依次增加。
        // 用于记录自己“抢锁”的Revision，初始值为0L
        long revisionOfMyself = 0L;

        KV kvClient = client.getKVClient();
        // lock，尝试加锁，加锁只关注Key，value不为空即可。
        // 注意：这里没有考虑可靠性和重试机制，实际应用中应考虑put操作而重试
        try
        {
            PutResponse putResponse = kvClient
                    .put(ByteSequence.fromString(strBufOfRealKey.toString()),
                            ByteSequence.fromString("value"),
                            PutOption.newBuilder().withLeaseId(leaseId).build())
                    .get(10, TimeUnit.SECONDS);

            // 获取自己加锁操作的Revision号
            revisionOfMyself = putResponse.getHeader().getRevision();
        }
        catch (InterruptedException | ExecutionException | TimeoutException e1)
        {
            System.out.println("[error]: lock operation failed:" + e1);
        }

        try
        {
            // lockName作为前缀，取出所有键值对，并且根据Revision进行升序排列，版本号小的在前
            List<KeyValue> kvList = kvClient.get(ByteSequence.fromString(lockName),
                    GetOption.newBuilder().withPrefix(ByteSequence.fromString(lockName))
                            .withSortField(SortTarget.MOD).build())
                    .get().getKvs();

            // 如果自己的版本号最小，则表明自己持有锁成功，否则进入监听流程，等待锁释放
            if (revisionOfMyself == kvList.get(0).getModRevision())
            {
                System.out.println("[lock]: lock successfully. [revision]:" + revisionOfMyself);
                // 加锁成功，返回实际存储于Etcd中的Key
                return strBufOfRealKey.toString();
            }
            else
            {
                // 记录自己加锁操作的前一个加锁操作的索引，因为只有前一个加锁操作完成并释放，自己才能获得锁
                int preIndex = 0;
                for (int index = 0; index < kvList.size(); index++)
                {                 
                    if (kvList.get(index).getModRevision() == revisionOfMyself)
                    {
                        preIndex = index - 1;// 前一个加锁操作，故比自己的索引小1
                    }
                }
                // 根据索引，获得前一个加锁操作对应的Key
                ByteSequence preKeyBS = kvList.get(preIndex).getKey();

                // 创建一个Watcher，用于监听前一个Key
                Watcher watcher = client.getWatchClient().watch(preKeyBS);
                WatchResponse res = null;
                // 监听前一个Key，将处于阻塞状态，直到前一个Key发生delete事件
                // 需要注意的是，一个Key对应的事件不只有delete，不过，对于分布式锁来说，除了加锁就是释放锁
                // 因此，这里只要监听到事件，必然是delete事件或者Key因租约过期而失效删除，结果都是锁被释放
                try
                {
                        System.out.println("[lock]: keep waiting until the lock is released.");
                    res = watcher.listen();
                }
                catch (InterruptedException e)
                {
                    System.out.println("[error]: failed to listen key.");
                }

                // 为了便于读者理解，此处写一点冗余代码，判断监听事件是否为DELETE，即释放锁
                List<WatchEvent> eventlist = res.getEvents();
                for (WatchEvent event : eventlist)
                {
                        // 如果监听到DELETE事件，说明前一个加锁操作完成并已经释放，自己获得锁，返回
                    if (event.getEventType().toString().equals("DELETE"))
                    {
                            System.out.println("[lock]: lock successfully. [revision]:" + revisionOfMyself);
                        return strBufOfRealKey.toString();
                    }
                }
            }
        }
        catch (InterruptedException | ExecutionException e)
        {
            System.out.println("[error]: lock operation failed:" + e);
        }

        return strBufOfRealKey.toString();
    }

    /**
     * 释放锁方法，本质上就是删除实际存储于Etcd中的Key
     * 
     * @param lockName
     * @param client
     */
    public static void unLock(String realLockName, Client client)
    {
        try
        {
            client.getKVClient().delete(ByteSequence.fromString(realLockName)).get(10,
                    TimeUnit.SECONDS);
            System.out.println("[unLock]: unlock successfully.[lockName]:" + realLockName);
        }
        catch (InterruptedException | ExecutionException | TimeoutException e)
        {
            System.out.println("[error]: unlock failed：" + e);
        }
    }

    /**
     * 自定义一个线程类，模拟分布式场景下多个进程 "抢锁"
     */
    public static class MyThread extends Thread
    {
        private String lockName;
        private Client client;

        MyThread(String lockName, Client client)
        {
            this.client = client;
            this.lockName = lockName;
        }

        @Override
        public void run()
        {
            // 创建一个租约，有效期15s
            Lease leaseClient = client.getLeaseClient();
            Long leaseId = null;
            try
            {
                leaseId = leaseClient.grant(15).get(10, TimeUnit.SECONDS).getID();
            } 
            catch (InterruptedException | ExecutionException | TimeoutException e1)
            {
                 System.out.println("[error]: create lease failed:" + e1);
                 return;
            }

            // 创建一个定时任务作为“心跳”，保证等待锁释放期间，租约不失效；
            // 同时，一旦客户端发生故障，心跳便会中断，锁也会应租约过期而被动释放，避免死锁
                ScheduledExecutorService service = Executors.newSingleThreadScheduledExecutor();  
            // 续约心跳为12s，仅作为举例  
            service.scheduleAtFixedRate(new KeepAliveTask(leaseClient, leaseId), 1, 12, TimeUnit.SECONDS); 

            // 1. try to lock
            String realLoclName = lock(lockName, client, leaseId);

            // 2. to do something
            try
            {
                Thread.sleep(6000);
            }
            catch (InterruptedException e2)
            {
                System.out.println("[error]:" + e2);
            }
            // 3. unlock
            service.shutdown();// 关闭续约的定时任务
            unLock(realLoclName, client);
        }
    }

    /**
     * 在等待其它客户端释放锁期间，通过心跳续约，保证自己的Key-Value不会失效
     *
     */
    public static class KeepAliveTask implements Runnable
    {
            private Lease leaseClient;
            private long leaseId;

            KeepAliveTask(Lease leaseClient, long leaseId)
            {
                this.leaseClient = leaseClient;
                this.leaseId = leaseId;
            }

            @Override
        public void run()
        {
                leaseClient.keepAliveOnce(leaseId);     
        }
    }
}



Demo 运行结果如下：

[lock]: lock successfully. [revision]:44
[lock]: keep waiting until the lock is released.
[lock]: keep waiting until the lock is released.
[unLock]: unlock successfully.
[lock]: lock successfully. [revision]:45
[unLock]: unlock successfully.
[lock]: lock successfully. [revision]:46
[unLock]: unlock successfully.



4. Etcd 实现分布式锁：路线二

Etcd 最新版本提供了支持分布式锁的基础接口，其本质就是将第 3 节（路线一）中介绍的实现细节进行了封装。单从使用的角度来看，这是非常有益的，大大降低了分布式锁的实现难度。但，与此同时，简化的接口也无形中为用户理解内部原理设置了屏障。

4.1 Etcd 提供的分布式锁基础接口

在介绍 jetcd 提供的 Lock 客户端之前，我们先用 Etcd 官方提供的 Go 语言客户端（etcdctl）验证一下分布式锁的实现原理。解压官方提供的 Etcd 安装包，里面有两个可执行文件：etcd 和 etcdctl，其中 etcd 是服务端，etcdctl 是客户端。在服务端启动的前提下，执行以下命令验证分布式锁原理。

（1）分别开启两个窗口，进入 etcdctl 所在目录，执行以下命令，显式指定 API 版本为 V3，老版本 V2 不支持分布式锁接口。

export ETCDCTL_API=3



（2）分别在两个窗口执行相同的加锁命令：

./etcdctl.exe lock mylock



（3）可以观察到，只有一个加锁成功，并返回了实际存储与 Etcd 中 Key 值：

$ ./etcdctl.exe lock mylock
mylock/694d65eb367c7ec4



（4）在加锁成功的窗口执行命令：Ctrl+c，释放锁；与此同时，另一个窗口加锁成功，如下所示：

$ ./etcdctl.exe lock mylock
mylock/694d65eb367c7ec8



很明显，同样的锁名“mylock”，两个客户端分别进行加锁操作，实际存储于 Etcd 中的 Key 并不是 “mylock”，而是以 “mylock” 为前缀，分别加了一个全局唯一的 ID。是不是和 “路线一” 中介绍得原理一致呢。

4.2 Etcd Java 客户端 jetcd 提供的 Lock 客户端

作为 Etcd 的 Java 客户端，jetcd 全面支持 Etcd 的 V3 接口，其中分布式锁相关的接口如下。看上去很简单，但事实上存在一个问题：租约没有心跳机制，在等待其它客户端释放锁期间，自己的租约存在过期的风险。鉴于此，需要进行改造。抛砖引玉，我在 4.3 节中提供了一个 Demo 供读者参考。

// 创建客户端，本例中Etcd服务端为单机模式
Client client = Client.builder().endpoints("http://localhost:2379").build();

// 创建Lock客户端
Lock lockClient = client.getLockClient();

// 创建Lease客户端，并创建一个有效期为30s的租约
Lease leaseClient = client.getLeaseClient()；
long leaseId = leaseClient.grant(30).get().getID();

// 加、解锁操作
try
{
    // 调用lock接口，加锁，并绑定租约
    lockClient.lock(ByteSequence.fromString("lockName"), leaseId).get();
    // 调用unlock接口，解锁
    lockClient.unlock(ByteSequence.fromString(lockName)).get();
}
catch (InterruptedException | ExecutionException e1)
{
    System.out.println("[error]: lock failed:" + e1);
}



4.3 基于 Etcd 的 Lock 接口实现分布式锁 Demo

第一部分：分布式锁实现

代码如下：

import java.util.concurrent.ExecutionException;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

import com.coreos.jetcd.Client;
import com.coreos.jetcd.Lease;
import com.coreos.jetcd.Lock;
import com.coreos.jetcd.data.ByteSequence;

public class DistributedLock
{
    private static DistributedLock lockProvider = null;
    private static Object mutex = new Object();
    private Client client;
    private Lock lockClient;
    private Lease leaseClient;

    private DistributedLock()
    {
        super();
        // 创建Etcd客户端，本例中Etcd集群只有一个节点
        this.client = Client.builder().endpoints("http://localhost:2379").build();
        this.lockClient = client.getLockClient();
        this.leaseClient = client.getLeaseClient();
    }

    public static DistributedLock getInstance()
    {
        synchronized (mutex)
        {
            if (null == lockProvider)
            {
                lockProvider = new DistributedLock();
            }
        }
        return lockProvider;
    }

    /**
     * 加锁操作，需要注意的是，本例中没有加入重试机制，加锁失败将直接返回。
     * 
     * @param lockName: 针对某一共享资源(数据、文件等)制定的锁名
     * @param TTL : Time To Live，租约有效期，一旦客户端崩溃，可在租约到期后自动释放锁
     * @return LockResult
     */
    public LockResult lock(String lockName, long TTL)
    {
        LockResult lockResult = new LockResult();
        /*1.准备阶段*/
        // 创建一个定时任务作为“心跳”，保证等待锁释放期间，租约不失效；
        // 同时，一旦客户端发生故障，心跳便会停止，锁也会因租约过期而被动释放，避免死锁
        ScheduledExecutorService service = Executors.newSingleThreadScheduledExecutor();

        // 初始化返回值lockResult
        lockResult.setIsLockSuccess(false);
        lockResult.setService(service);

        // 记录租约ID，初始值设为 0L
        Long leaseId = 0L;

        /*2.创建租约*/
        // 创建一个租约，租约有效期为TTL，实际应用中根据具体业务确定。
        try
        {
            leaseId = leaseClient.grant(TTL).get().getID();
            lockResult.setLeaseId(leaseId);

            // 启动定时任务续约，心跳周期和初次启动延时计算公式如下，可根据实际业务制定。
            long period = TTL - TTL / 5;
            service.scheduleAtFixedRate(new KeepAliveTask(leaseClient, leaseId), period, period,
                    TimeUnit.SECONDS);
        }
        catch (InterruptedException | ExecutionException e)
        {
            System.out.println("[error]: Create lease failed:" + e);
            return lockResult;
        }

        System.out.println("[lock]: start to lock." + Thread.currentThread().getName());

        /*3.加锁操作*/
        // 执行加锁操作，并为锁对应的Key绑定租约
        try
        {
            lockClient.lock(ByteSequence.fromString(lockName), leaseId).get();
        }
        catch (InterruptedException | ExecutionException e1)
        {
            System.out.println("[error]: lock failed:" + e1);
            return lockResult;
        }
        System.out.println("[lock]: lock successfully." + Thread.currentThread().getName());

        lockResult.setIsLockSuccess(true);

        return lockResult;
    }

    /**
     * 解锁操作，释放锁、关闭定时任务、解除租约
     * 
     * @param lockName:锁名
     * @param lockResult:加锁操作返回的结果
     */
    public void unLock(String lockName, LockResult lockResult)
    {
        System.out.println("[unlock]: start to unlock." + Thread.currentThread().getName());
        try
        {
            // 释放锁   
            lockClient.unlock(ByteSequence.fromString(lockName)).get();
            // 关闭定时任务
            lockResult.getService().shutdown();
            // 删除租约
            if (lockResult.getLeaseId() != 0L)
            {
                leaseClient.revoke(lockResult.getLeaseId());
            }
        }
        catch (InterruptedException | ExecutionException e)
        {
            System.out.println("[error]: unlock failed: " + e);
        }

        System.out.println("[unlock]: unlock successfully." + Thread.currentThread().getName());
    }

    /**
     * 在等待其它客户端释放锁期间，通过心跳续约，保证自己的锁对应租约不会失效
     *
     */
    public static class KeepAliveTask implements Runnable
    {
        private Lease leaseClient;
        private long leaseId;

        KeepAliveTask(Lease leaseClient, long leaseId)
        {
            this.leaseClient = leaseClient;
            this.leaseId = leaseId;
        }

        @Override
        public void run()
        {
            // 续约一次
            leaseClient.keepAliveOnce(leaseId);
        }
    }

    /**
     * 该class用于描述加锁的结果，同时携带解锁操作所需参数
     * 
     */
    public static class LockResult
    {
        private boolean isLockSuccess;
        private long leaseId;
        private ScheduledExecutorService service;

        LockResult()
        {
            super();
        }

        public void setIsLockSuccess(boolean isLockSuccess)
        {
            this.isLockSuccess = isLockSuccess;
        }

        public void setLeaseId(long leaseId)
        {
            this.leaseId = leaseId;
        }

        public void setService(ScheduledExecutorService service)
        {
            this.service = service;
        }

        public boolean getIsLockSuccess()
        {
            return this.isLockSuccess;
        }

        public long getLeaseId()
        {
            return this.leaseId;
        }

        public ScheduledExecutorService getService()
        {
            return this.service;
        }
    }

}



第二部分：测试代码

代码如下：

public class DistributedLockTest
{

    public static void main(String[] args)
    {
        // 模拟分布式场景下，多个进程 “抢锁”
        for (int i = 0; i < 5; i++)
        {
            new MyThread().start();
        }
    }

    public static class MyThread extends Thread
    {
        @Override
        public void run()
        {
            String lockName = "/lock/mylock";
            // 1. 加锁
            LockResult lockResult = DistributedLock.getInstance().lock(lockName, 30);

            // 2. 执行业务
            if (lockResult.getIsLockSuccess())
            {
                // 获得锁后，执行业务，用sleep方法模拟.
                try
                {
                    Thread.sleep(10000);
                }
                catch (InterruptedException e)
                {
                    System.out.println("[error]:" + e);
                }
            }

            // 3. 解锁
            DistributedLock.getInstance().unLock(lockName, lockResult);
        }
    }
}



第三部分：测试结果

[lock]: start to lock.Thread-4
[lock]: start to lock.Thread-3
[lock]: start to lock.Thread-1
[lock]: start to lock.Thread-0
[lock]: start to lock.Thread-2
[lock]: lock successfully.Thread-3
[unlock]: start to unlock.Thread-3
[unlock]: unlock successfully.Thread-3
[lock]: lock successfully.Thread-2
[unlock]: start to unlock.Thread-2
[unlock]: unlock successfully.Thread-2
[lock]: lock successfully.Thread-1
[unlock]: start to unlock.Thread-1
[unlock]: unlock successfully.Thread-1
[lock]: lock successfully.Thread-0
[unlock]: start to unlock.Thread-0
[unlock]: unlock successfully.Thread-0
[lock]: lock successfully.Thread-4
[unlock]: start to unlock.Thread-4
[unlock]: unlock successfully.Thread-4


                        
                        
                            