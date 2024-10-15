
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            08 基于 Redis 的分布式锁实现及其踩坑案例
                            分布式锁的实现，目前常用的方案有以下三类：


数据库乐观锁；
基于分布式缓存实现的锁服务，典型代表有 Redis 和基于 Redis 的 RedLock；
基于分布式一致性算法实现的锁服务，典型代表有 ZooKeeper、Chubby 和 ETCD。



基于 Redis 实现分布式锁，网上可以查到很多相关资料，我最初也借鉴了这些资料，但是，在分布式锁的实现和使用过程中意识到这些资料普遍存在问题，容易误导初学者，鉴于此，撰写了本文，希望为对分布式锁感兴趣的读者提供一篇切实可用的参考文档。


1. 分布式锁原理介绍

1.1 分布式锁基本约束条件

为了确保锁服务可用，通常，分布式锁需同时满足以下四个约束条件。


互斥性：在任意时刻，只有一个客户端能持有锁；
安全性：即不会形成死锁，当一个客户端在持有锁的期间崩溃而没有主动解锁的情况下，其持有的锁也能够被正确释放，并保证后续其它客户端能加锁；
可用性：就 Redis 而言，当提供锁服务的 Redis Master 节点发生宕机等不可恢复性故障时，Slave 节点能够升主并继续提供服务，支持客户端加锁和解锁；对基于分布式一致性算法实现的锁服务（如 ETCD）而言，当 Leader 节点宕机时，Follow 节点能够选举出新的 Leader 继续提供锁服务；
对称性：对于任意一个锁，其加锁和解锁必须是同一个客户端，即客户端 A 不能把客户端 B 加的锁给解了。


1.2 基于 Redis 实现分布式锁（以 Redis 单机模式为例）

基于 Redis 实现锁服务的思路比较简单。我们把锁数据存储在分布式环境中的一个节点，所有需要获取锁的调用方（客户端），都需访问该节点，如果锁数据（Key-Value 键值对）已经存在，则说明已经有其它客户端持有该锁，可等待其释放（Key-Value 被主动删除或者因过期而被动删除）再尝试获取锁；如果锁数据不存在，则写入锁数据（Key-Value），其中 Value 需要保证在足够长的一段时间内在所有客户端的所有获取锁的请求中都是唯一的，以便释放锁的时候进行校验；锁服务使用完毕之后，需要主动释放锁，即删除存储在 Redis 中的 Key-Value 键值对。其架构如下：



1.3 加解锁流程

根据 Redis 官方的文档，获取锁的操作流程如下。

步骤1，向 Redis 节点发送命令，请求锁。代码如下：

SET lock_name my_random_value NX PX 30000



下面解释下各参数的意义。


lock_name，即锁名称，这个名称应是公开的，在分布式环境中，对于某一确定的公共资源，所有争用方（客户端）都应该知道对应锁的名字。对于 Redis 而言，lock_name 就是 Key-Value 中的 Key，具有唯一性。
my_random_value 是由客户端生成的一个随机字符串，它要保证在足够长的一段时间内，且在所有客户端的所有获取锁的请求中都是唯一的，用于唯一标识锁的持有者。
NX 表示只有当 lock_name(key) 不存在的时候才能 SET 成功，从而保证只有一个客户端能获得锁，而其它客户端在锁被释放之前都无法获得锁。
PX 30000 表示这个锁节点有一个 30 秒的自动过期时间（目的是为了防止持有锁的客户端故障后，无法主动释放锁而导致死锁，因此要求锁的持有者必须在过期时间之内执行完相关操作并释放锁）。


步骤2，如果步骤 1 的命令返回成功，则代表获取锁成功，否则获取锁失败。

对于一个拥有锁的客户端，释放锁流程如下。

（1）向 Redis 节点发送命令，获取锁对应的 Value，代码如下：

GET lock_name



（2）如果查询回来的 Value 和客户端自身的 my_random_value 一致，则可确认自己是锁的持有者，可以发起解锁操作，即主动删除对应的 Key，发送命令：

DEL lock_name



通过 Redis-cli 执行上述命令，显示如下：

100.X.X.X:6379> set lock_name my_random_value NX PX 30000
OK
100.X.X.X:6379> get lock_name
"my_random_value"
100.X.X.X:6379> del lock_name
(integer) 1
100.X.X.X:6379> get lock_name
(nil)



2. 基于 Redis 的分布式锁的安全性分析

2.1 预防死锁

我们看下面这个典型死锁场景。


一个客户端获取锁成功，但是在释放锁之前崩溃了，此时该客户端实际上已经失去了对公共资源的操作权，但却没有办法请求解锁（删除 Key-Value 键值对），那么，它就会一直持有这个锁，而其它客户端永远无法获得锁。


我们的解决方案是：在加锁时为锁设置过期时间，当过期时间到达，Redis 会自动删除对应的 Key-Value，从而避免死锁。需要注意的是，这个过期时间需要结合具体业务综合评估设置，以保证锁的持有者能够在过期时间之内执行完相关操作并释放锁。

2.2 设置锁自动过期时间以预防死锁存在的隐患

为了避免死锁，可利用 Redis 为锁数据（Key-Value）设置自动过期时间，虽然可以解决死锁的问题，但却存在隐患。

我们看下面这个典型场景。


客户端 A 获取锁成功；
客户端 A 在某个操作上阻塞了很长时间（对于 Java 而言，如发生 Full-GC）；
过期时间到，锁自动释放；
客户端 B 获取到了对应同一个资源的锁；
客户端 A 从阻塞中恢复过来，认为自己依旧持有锁，继续操作同一个资源，导致互斥性失效。


这时我们可采取的解决方案见下。


存在隐患的方案。第 5 步中，客户端 A 恢复后，可以比较下目前已经持有锁的时间，如果发现已经过期，则放弃对共享资源的操作即可避免互斥性失效的问题。但是，客户端 A 所在节点的时间和 Redis 节点的时间很可能不一致（比如客户端与 Redis 节点不在同一台服务器，而不同服务器时间通常不完全同步），因此，严格来讲，任何依赖两个节点时间比较结果的互斥性算法，都存在隐患。目前网上很多资料都采用了这种方案，鉴于其隐患，不推荐。
可取的方案。既然比较时间不可取，那么，还可以比较 my_random_value，即客户端 A 恢复后，在操作共享资源前应比较目前自身所持有锁的 my_random_value 与 Redis 中存储的 my_random_value 是否一致，如果不相同，说明已经不再持有锁，则放弃对共享资源的操作以避免互斥性失效的问题。


2.3 解锁操作的原子性

为了保证每次解锁操作都能正确进行，需要引入全局唯一变量 my_random_value。具体而言，解锁需要两步，先查询（GET）锁对应的 Value，与自己加锁时设置的 my_random_value 进行对比，如果相同，则可确认这把锁是自己加的，然后再发起解锁（DEL）。需要注意的是，GET 和 DEL 是两个操作，非原子性，那么解锁本身也会存在破坏互斥性的可能。

下面是典型场景。


客户端 A 获取锁成功；
客户端 A 访问共享资源；
客户端 A 为了释放锁，先执行 GET 操作获取锁对应的随机字符串的值；
客户端 A 判断随机字符串的值，与预期的值相等；
客户端 A 由于某个原因阻塞了很长时间；
过期时间到了，锁自动释放了；
客户端 B 获取到了对应同一个资源的锁；
客户端 A 从阻塞中恢复过来，执行 DEL 操纵，释放掉了客户端 B 持有的锁。


下面给出解决方案。

如何保障解锁操作的原子性呢？在实践中，我总结出两种方案。

1. 使用 Redis 事务功能，使用 Watch 命令监控锁对应的 Key，释放锁则采用事务功能（Multi 命令），如果持有的锁已经因过期而释放（或者过期释放后又被其它客户端持有），则 Key 对应的 Value 将改变，释放锁的事务将不会被执行，从而避免错误的释放锁，示例代码如下：

Jedis jedis = new Jedis("127.0.0.1", 6379);

// “自旋”，等待锁
String result = null;
while (true)
{
    // 申请锁，只有当“lock_name”不存在时才能申请成功，返回“OK",锁的过期时间设置为5s
    result = jedis.set("lock_name", "my_random_value", SET_IF_NOT_EXIST,
            SET_WITH_EXPIRE_TIME, 5000);
    if ("OK".equals(result))
    {
        break;
    }
}

// 监控锁对应的 Key，如果其它的客户端对这个 Key 进行了更改，那么本次事务会被取消。
jedis.watch("lock_name");
// 成功获取锁，则操作公共资源，自定义流程
// to do something...

// 释放锁之前，校验是否持有锁
if (jedis.get("lock_name").equals("my_random_value"))
{
    // 开启事务功能，
    Transaction multi = jedis.multi();
    // 模拟客户端阻塞10s，锁超时，自动清除
    try
    {
        Thread.sleep(5000);
    }
    catch (InterruptedException e)
    {
        e.printStackTrace();
    }
    // 客户端恢复，继续释放锁
    multi.del("lock_name");
    // 执行事务（如果其它的客户端对这个Key进行了更改，那么本次事务会被取消,不会执行)
    multi.exec();
}

// 释放资源
jedis.unwatch();
jedis.close();



2. Redis 支持 Lua 脚本并保证其原子性，使用 Lua 脚本实现锁校验与释放，并使用 Redis 的 eval 函数执行 Lua 脚本，代码如下：

Jedis jedis = new Jedis("127.0.0.1", 6379);

// “自旋”，等待锁
String result = null;
while (true)
{
    // 申请锁，只有当“lock_name”不存在时才能申请成功，返回“OK"，锁的过期时间设置为 5s
    result = jedis.set("lock_name", "my_random_value", SET_IF_NOT_EXIST,
            SET_WITH_EXPIRE_TIME, 5000);
    if ("OK".equals(result))
    {
        break;
    }
}
// 成功获取锁，则操作公共资源，自定义流程
// to do something...

// Lua脚本，用于校验并释放锁     
String script = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";
try
{
    // 模拟客户端阻塞10s，锁超时，自动清除
    Thread.sleep(10000);
}
catch (InterruptedException e)
{
    e.printStackTrace();
}

// 执行Lua脚本，校验并释放锁
jedis.eval(script, Collections.singletonList("lock_name"),
        Collections.singletonList("my_random_value"));

jedis.close();



2.4 Redis 节点故障后，主备切换的数据一致性

考虑 Redis 节点宕机，如果长时间无法恢复，则导致锁服务长时间不可用。为了保证锁服务的可用性，通常的方案是给这个 Redis 节点挂一个 Slave（多个也可以），当 Master 节点不可用的时候，系统自动切到 Slave 上。但是由于 Redis 的主从复制（Replication）是异步的，这可能导致在宕机切换过程中丧失锁的安全性。

我们看下典型场景。


客户端 A 从 Master 获取了锁；
Master 宕机了，存储锁的 Key 还没有来得及同步到 Slave 上；
Slave 升级为 Master；
客户端 B 从新的 Master 获取到了对应同一个资源的锁；
客户端 A 和客户端 B 同时持有了同一个资源的锁，锁的安全性被打破。


解决方案有两个。

方案1，设想下，如果要避免上述情况，可以采用一个比较“土”的方法，即自认为持有锁的客户端在对敏感公共资源进行写操作前，先进行校验，确认自己是否确实持有锁，校验的方式前面已经介绍过——通过比较自己的 my_random_value 和 Redis 服务端中实际存储的 my_random_value。

显然，这里仍存在一个问题。如果校验完毕后，Master 数据尚未同步到 Slave 的情况下 Master 宕机，该如何是好？诚然，我们可以为 Redis 服务端设置较短的主从复置周期，以尽量避免上述情况出现，但是，隐患还是客观存在的。

方案2，针对该问题场景，Redis 的作者 Antirez 提出了 RedLock，其原理基于分布式一致性算法的核心理念：多数派思想。下面对 RedLock 做简要介绍。

2.5 RedLock 简要介绍

2.4 节介绍了基于单 Redis 节点的分布式锁在主从故障倒换（Failover）时会产生安全性问题。针对问题场景，Redis 的作者 Antirez 提出了 RedLock，它基于 N 个完全独立的 Redis 节点，其原理基于分布式一致性算法的核心理念：多数派思想，不过，RedLock 目前还不成熟，争议较大，本节仅作简要介绍。

运行 Redlock 算法的客户端依次执行以下步骤，来进行加锁的操作：


获取当前系统时间（毫秒数）。
按顺序依次向 N 个 Redis 节点执行获取锁的操作。这个获取操作跟前面基于单 Redis 节点获取锁的过程相同，包含随机字符串 my_random_value，也包含过期时间（比如 PX 30000，即锁的有效时间）。为了保证在某个 Redis 节点不可用的时候算法能够继续运行，这个获取锁的操作还有一个超时时间（Time Out），它要远小于锁的有效时间（几十毫秒量级）。客户端在向某个 Redis 节点获取锁失败以后，应该立即尝试下一个 Redis 节点。这里的失败，应该包含任何类型的失败，比如该 Redis 节点不可用。
计算获取锁的整个过程总共消耗了多长时间，计算方法是用当前时间减去第 1 步记录的时间。如果客户端从大多数 Redis 节点（>=N/2+1）成功获取到了锁，并且获取锁总共消耗的时间没有超过锁的有效时间（Lock Validity Time），那么这时客户端才认为最终获取锁成功；否则，认为最终获取锁失败。
如果最终获取锁成功了，那么这个锁的有效时间应该重新计算，它等于最初的锁的有效时间减去第 3 步计算出来的获取锁消耗的时间。
如果最终获取锁失败了（可能由于获取到锁的 Redis 节点个数少于 N/2+1，或者整个获取锁的过程消耗的时间超过了锁的最初有效时间），那么客户端应该立即向所有 Redis 节点发起释放锁的操作（即前面介绍的 Redis Lua 脚本）。


我们再来了解下解锁步骤。上面描述的只是获取锁的过程，而释放锁的过程比较简单，即客户端向所有 Redis 节点发起释放锁的操作，不管这些节点在获取锁的时候成功与否。

该方法在理论上的可靠性如何呢？

N 个 Redis 节点中的大多数能正常工作，就能保证 Redlock 正常工作，因此理论上它的可用性更高。2.4 节中所描述的问题在 Redlock 中就不存在了，但如果有节点发生崩溃重启，还是会对锁的安全性有影响的。

它有哪些潜在问题呢，我们来看下面这个例子。

从加锁的过程，读者应该可以看出：RedLock 对系统时间是强依赖的，那么，一旦节点系统时间出现异常（Redis 节点不在同一台服务器上），问题便又来了，如下场景，假设一共有 5 个 Redis 节点：A、B、C、D、E。


客户端 1 成功锁住了 A、B、C，获取锁成功（但 D 和 E 没有锁住）。
节点 C 时间异常，导致 C 上的锁数据提前到期，而被释放。
客户端 2 此时尝试获取同一把锁：锁住了C、D、E，获取锁成功。


3. 加锁的正确方式及典型错误

3.1 客户端选择

这里，我选用了 Redis 开源客户端 Jedis，读者在运行示例代码前，需在对应的 Maven 工程的 Pom 文件中加入如下依赖：

<dependency>
    <groupId>redis.clients</groupId>
    <artifactId>jedis</artifactId>
    <version>2.9.0</version>
</dependency>



Jedis 是一个优秀的基于 Java 语言的 Redis 客户端。但是，其不足也很明显，Jedis 在实现上是直接连接 Redis-Server，在多个线程间共享一个 Jedis 实例时是线程不安全的，如果想要在多线程场景下使用 Jedis，需要使用连接池，每个线程都使用自己的 Jedis 实例，当连接数量增多时，会消耗较多的物理资源。本文中使用 Jedis，采用的是连接池模式。如下代码：

JedisPoolConfig config = new JedisPoolConfig();
// 设置最大连接数
config.setMaxTotal(200);
// 设置最大空闲数
config.setMaxIdle(8);
// 设置最大等待时间
config.setMaxWaitMillis(1000 * 100);
// 在borrow一个jedis实例时，是否需要验证，若为true，则所有jedis实例均是可用的
config.setTestOnBorrow(true);
// 创建连接池
JedisPool jedisPool = new JedisPool(config, "127.0.0.1", 6379, 3000);



3.2 正确的加锁方式

基于第 2 节《基于 Redis 的分布式锁的安全性分析》，我们很容易写出以下加锁代码：

public class DistributedLock
{
    private static final String LOCK_SUCCESS = "OK";
    private static final String SET_IF_NOT_EXIST = "NX";
    private static final String SET_WITH_EXPIRE_TIME = "PX";
    /**
     * 加锁
     * @param jedisPool jedis 连接池
     * @param lockName 锁名，对应被争用的公共资源
     * @param myRandomValue 需保持全局唯一，以校验锁的持有者
     * @param expireTime 过期时间。过期将自动删除（释放锁）
     */
    public static void Lock(JedisPool jedisPool, String lockName, String myRandomValue,
            int expireTime)
    {
        Jedis jedis = null;
        try
        {
            jedis = jedisPool.getResource();
            // "自旋"，等待锁
            while (true)
            {
                String result = jedis.set(lockName, myRandomValue, SET_IF_NOT_EXIST,
                        SET_WITH_EXPIRE_TIME, expireTime);

                if (LOCK_SUCCESS.equals(result))
                {
                    return;
                }
            }
        }
        catch (Exception e)
        {
            throw e;
        }
        finally
        {
            if (null != jedis)
            {
                jedis.close();
            }
        }
    }
}



加锁核心方法为：

jedis.set(String key, String value, String nxxx, String expx, int time)



接下来说明下各个参数的意义。


key：Redis 是 Key-Value 型数据库，key 具有唯一性，因此，用 key 作为锁。
value：即例子中的 my_random_value，在 2.2 节《设置锁自动过期时间以预防死锁存在的隐患》中，我分析了隐患场景并给出了解决方案。为了保障可靠性，在解锁时，仅仅依赖 Key 是不够的，为了避免错误得释放锁，释放前需要进行校验，即根据 Key 取出 Value，将其与自己加锁时设置的 my_random_value 进行对比，以便确认是否是自己持有的锁。my_random_value 可以使用特定的随机算法生成，如 UUID.randomUUID().toString()。
nxxx：根据 Redis 文档，这个参数填 NX，意思是 SET IF NOT EXIST，即当 Key 不存在时方可进行 SET 操作；若 Key 已经存在，则不做任何操作；
expx：这个参数传的是 PX，表示给 Key 设置过期时间，具体时间由参数 time 决定。
time：与参数 expx 相呼应，代表 Key 的过期时间，单位为毫秒。


最后，我们做下小结。

通过上述说明，set(…) 方法可以满足加锁的安全性，执行 set(…) 方法有两种结果。


如果被争用的公共资源没有锁（即 Key 不存在），那么就进行加锁操作，并对锁设置个有效期，同时用具有特异性（一段时间内具有唯一性）Value 来标识加锁的客户端，以便解锁时进行校验。
如果被争用的公共资源已经被加锁（即 Key 存在），则不做任何操作，通常的做法是等待锁释放，采用不断轮询的方式来确定锁是否释放，这种方式也被称为“自旋”等待。此外，还可以设置一个超时时间，如果在超时时间内未能加锁成功则退出。


3.3 典型错误案例

分别使用 jedis.setnx() 和 jedis.expire() 组合实现加锁，代码如下：

public static void lock(JedisPool jedisPool, String lockName, String myRandomValue, int expireTime) 
    {
        Jedis jedis = jedisPool.getResource();
        // 如果锁不存在，则加锁
        Long result = jedis.setnx(lockName, myRandomValue);
        if (result == 1) 
        {
            // 为锁设置过期时间，由于加锁和设置过期时间是两步完成的，非原子操作
            jedis.expire(lockName, expireTime);
        }
    }



setnx() 方法的作用就是 SET IF NOT EXIST，expire() 方法就是给锁加一个过期时间。初看，似乎没有什么问题，但经不起推敲：加锁实际上使用了两条 Redis 命令，非原子性，如果程序在执行完 setnx() 之后突然崩溃，导致锁没有设置过期时间，那么将会造成死锁。

网上很多资料中采用的就是这种最初级的实现方式，读者切勿仿效。

4. 解锁代码

在 2.3 节《解锁操作的原子性》中，我曾分析了解锁操作可能出现的异常，并给出了两种解决方案，在此，我们再介绍下完整代码。

4.1 正确的解锁方式一

Redis 支持 Lua 脚本并保证其原子性，使用 Lua 脚本实现锁校验与释放，并使用 Redis 的 eval() 函数执行 Lua 脚本，代码如下：

public class DistributedLock
{
    // 释放锁成功标志
    private static final Long RELEASE_SUCCESS = 1L;
    /**
     * 释放锁
     * @param jedisPool jedis连接池
     * @param lockName 锁名，对应被争用的公共资源
     * @param myRandomValue 需保持全局唯一，以校验锁的持有者
     * @return 是否释放成功
     */
    public static boolean unLock(JedisPool jedisPool, String lockName, String myRandomValue)
    {

        Jedis jedis = null;
        // Lua脚本，用于校验并释放锁
        String script = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";
        try
        {
            jedis = jedisPool.getResource();
            Object result = jedis.eval(script, Collections.singletonList(lockName),
                    Collections.singletonList(myRandomValue));

            // 注意:如果脚本顺利执行将返回1，如果执行脚本中，其它的客户端对这个lockName对应的值进行了更改，那么将返回0
            if (RELEASE_SUCCESS.equals(result))
            {
                return true;
            }
        }
        catch (Exception e)
        {
            throw e;
        }
        finally
        {
            if (null != jedis)
            {
                jedis.close();
            }
        }

        return false;
    }
}



从上面的示例代码可以看出，解锁操作只用了两行代码。

第一行使用了 Lua 脚本，其语义为通过 GET 命令访问参数 KEYS[1] 对应的锁，获得锁对应的 Value，并将其与参数 ARGV[1] 对比，如果相同则调用 DEL 命令删除 KEYS[1] 对应的键值对（即释放锁操作）。

// Lua脚本，用于校验并释放锁
String script = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";



第二行通过 Redis 的 eval() 函数执行 Lua 脚本，其中入参 lockName 赋值给参数 KEYS[1]，myRandomValue 赋值给 ARGV[1]，eval() 函数将 Lua 脚本交给 Redis 服务端执行。

jedis.eval(script, Collections.singletonList(lockName), Collections.singletonList(myRandomValue));



根据 Redis 官网文档说明，通过 eval() 执行 Lua 代码时，Lua 代码将被当成一个命令去执行（可保证原子性），并且直到 eval 命令执行完成，Redis 才会执行其他命令。因此，通过 Lua 脚本结合 eval 函数，可以科学得实现解锁操作的原子性，避免误解锁。

4.2 正确的解锁方式二

使用 Redis 事务功能，通过 Watch 命令监控锁对应的 Key，释放锁则采用事务功能（Multi 命令），如果持有的锁已经因过期而释放（也可能释放后又被其它客户端持有），则 Key 对应的 Value 将改变，释放锁的事务将不会被执行，从而保证原子性，同时避免错误的释放锁，示例代码如下：

public class DistributedLock
{
    private static final Long RELEASE_SUCCESS = 1L;
    /**
     * 释放锁
     * @param jedisPool jedis连接池
     * @param lockName 锁名，对应被争用的公共资源
     * @param myRandomValue 需保持全局唯一，以校验锁的持有者
     * @return 是否释放成功
     */
    public static boolean unLockII(JedisPool jedisPool, String lockName, String myRandomValue)
    {
        Jedis jedis = null;     
        try
        {
            jedis = jedisPool.getResource();

            // 监控锁对应的Key，如果其它的客户端对这个Key进行了更改，那么本次事务会被取消。
            jedis.watch(lockName);
            // 成功获取锁，则操作公共资源，自定义流程
            // to do something...

            // 校验是否持有锁
            if (myRandomValue.equals(jedis.get(lockName)))
            {
                // 开启事务功能，
                Transaction multi = jedis.multi();
                // 释放锁
                multi.del(lockName);
                // 执行事务（如果其它的客户端对这个Key进行了更改，那么本次事务会被取消,不会执行)
                // 如果正常执行，由于只有一个删除操作，返回的list将只有一个对象。
                List<Object> result = multi.exec();
                if (RELEASE_SUCCESS.equals(result.size()))
                {
                    return true;
                }
            }
        }
        catch (Exception e)
        {
            throw e;
        }
        finally
        {
            if (null != jedis)
            {
                jedis.unwatch();
                jedis.close();
            }
        }

        return false;
    }
}



这里稍微解释下。


参考百度百科，所谓事务，应该具有 4 个属性，即原子性、一致性、隔离性、持久性。这四个属性通常称为 ACID 特性。



原子性（Atomicity）：一个事务是一个不可分割的工作单位，事务中包括的诸操作要么都做，要么都不做。
一致性（Consistency）：事务必须使数据库从一个一致性状态变到另一个一致性状态。一致性与原子性是密切相关的。
隔离性（Isolation）：一个事务的执行不能被其他事务干扰。即一个事务内部的操作及使用的数据对并发的其他事务是隔离的，并发执行的各个事务之间不能互相干扰。
持久性（Durability）：持久性也称永久性（Permanence），指一个事务一旦提交，它对数据库中数据的改变就应该是永久性的。接下来的其他操作或故障不应该对其有任何影响。


Redis 支持事务功能，根据事务所具有特征，读者应该可以发现，我们解锁时最关心的所有问题，事务都可以解决。这也是我介绍事务功能解锁的原因。Redis 使用事务功能，通常采用的步骤如下。

步骤1，Watch 命令监控锁。

监控锁对应的 key(lockName)，事务开启后，如果其它的客户端对这个 Key 进行了更改，那么本次事务会被取消而不会执行 jedis.watch(lockName)。

步骤2，开启事务功能，代码如下：

jedis.multi()



步骤3，释放锁。

注意，事务开启后，释放锁的操作便是事务中的一个元素，隶属于该事务，代码如下：

multi.del(lockName);



步骤4，执行事务，代码如下：

multi.exec();



步骤5，释放资源，代码如下：

jedis.unwatch();
jedis.close();



4.3 典型解锁错误案例一

直接使用 jedis.del() 方法删除锁，而没有进行校验。在 2.3 节所述的异常场景下，这种不校验锁的拥有者而直接解锁的方式，会导致锁被错误的释放，从而破坏互斥性，如下面代码所示。

 public static void unLock(JedisPool jedisPool, String lockName)
 {
        Jedis jedis = jedisPool.getResource();
        jedis.del(lockName);
 }



4.4 典型解锁错误案例二

如下解锁方式相较于上一种已经有了明显进步，在解锁之前进行了校验。但是问题并没有解决，整个解锁过程仍然是独立的两条命令，并非原子操作。代码如下：

public static void unLock1(JedisPool jedisPool, String lockName, String myRandomValue)
{
    Jedis jedis = jedisPool.getResource();
    // 判断加锁与解锁是不是同一个客户端
    if (myRandomValue.equals(jedis.get(lockName))) 
    {
        // 解锁，如果在此之前出现异常而使客户端阻塞，锁已经过期被自动释放，本客户端已经不再持有锁，则会误解锁
        jedis.del(lockName);
    }
}



致谢

本文引用了以下文档中的一些图片和文字，一一列出，以表敬意。


官方文档：Distributed locks with Redis
官方文档：EVAL command


                        
                        
                            