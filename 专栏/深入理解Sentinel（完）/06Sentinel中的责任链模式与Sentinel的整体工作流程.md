
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            06 Sentinel 中的责任链模式与 Sentinel 的整体工作流程
                            上一篇我们简单了解了 ProcessorSlot，并且将 Sentinel 提供的所有 ProcessorSlot 分成两类，一类是辅助完成资源指标数据统计的 ProcessorSlot，一类是实现降级功能的 ProcessorSlot。

Sentinel 的整体工具流程就是使用责任链模式将所有的 ProcessorSlot 按照一定的顺序串成一个单向链表。辅助完成资源指标数据统计的 ProcessorSlot 必须在实现降级功能的 ProcessorSlot 的前面，原因很简单，降级功能需要依据资源的指标数据做判断，当然，如果某个 ProcessorSlot 不依赖指标数据实现降级功能，那这个 ProcessorSlot 的位置就没有约束。

除了按分类排序外，同一个分类下的每个 ProcessorSlot 可能也需要有严格的排序。比如辅助完成资源指标数据统计的 ProcessorSlot 的排序顺序为：


NodeSelectorSlot->ClusterBuilderSlot->StatisticSlot


如果顺序乱了就会抛出异常，而实现降级功能的 ProcessorSlot 就没有严格的顺序要求，AuthoritySlot、SystemSlot、FlowSlot、DegradeSlot 这几个的顺序可以按需调整。

实现将 ProcessorSlot 串成一个单向链表的是 ProcessorSlotChain，这个 ProcessorSlotChain 是由 SlotChainBuilder 构造的，默认 SlotChainBuilder 构造的 ProcessorSlotChain 注册的 ProcessorSlot 以及顺序如下代码所示。

public class DefaultSlotChainBuilder implements SlotChainBuilder {
    @Override
    public ProcessorSlotChain build() {
        ProcessorSlotChain chain = new DefaultProcessorSlotChain();
        chain.addLast(new NodeSelectorSlot());
        chain.addLast(new ClusterBuilderSlot());
        chain.addLast(new LogSlot());
        chain.addLast(new StatisticSlot());
        chain.addLast(new AuthoritySlot());
        chain.addLast(new SystemSlot());
        chain.addLast(new FlowSlot());
        chain.addLast(new DegradeSlot());
        return chain;
    }
}



如何去掉 ProcessorSlot 或者添加自定义的 ProcessorSlot，下一篇再作介绍。

ProcessorSlot 接口的定义如下：

public interface ProcessorSlot<T> {
    // 入口方法
    void entry(Context context, ResourceWrapper resourceWrapper, T param, int count, boolean prioritized,Object... args) throws Throwable;
    // 调用下一个 ProcessorSlot#entry 方法
    void fireEntry(Context context, ResourceWrapper resourceWrapper, Object obj, int count, boolean prioritized,Object... args) throws Throwable;
    // 出口方法
    void exit(Context context, ResourceWrapper resourceWrapper, int count, Object... args);
    // 调用下一个 ProcessorSlot#exit 方法
    void fireExit(Context context, ResourceWrapper resourceWrapper, int count, Object... args);
}



例如实现熔断降级功能的 DegradeSlot，其在 entry 方法中检查资源当前统计的指标数据是否达到配置的熔断降级规则的阈值，如果是则触发熔断，抛出一个 DegradeException（必须是 BlockException 的子类），而 exit 方法什么也不做。

方法参数解析：


context：当前调用链路上下文。
resourceWrapper：资源 ID。
param：泛型参数，一般用于传递 DefaultNode。
count：Sentinel 将需要被保护的资源包装起来，这与锁的实现是一样的，需要先获取锁才能继续执行。而 count 则与并发编程 AQS 中 tryAcquire 方法的参数作用一样，count 表示申请占用共享资源的数量，只有申请到足够的共享资源才能继续执行。例如，线程池有 200 个线程，当前方法执行需要申请 3 个线程才能执行，那么 count 就是 3。count 的值一般为 1，当限流规则配置的限流阈值类型为 threads 时，表示需要申请一个线程，当限流规则配置的限流阈值类型为 qps 时，表示需要申请 1 令牌（假设使用令牌桶算法）。
prioritized：表示是否对请求进行优先级排序，SphU#entry 传递过来的值是 false。
args：调用方法传递的参数，用于实现热点参数限流。


ProcessorSlotChain

之所以能够将所有的 ProcessorSlot 构造成一个 ProcessorSlotChain，还是依赖这些 ProcessorSlot 继承了 AbstractLinkedProcessorSlot 类。每个 AbstractLinkedProcessorSlot 类都有一个指向下一个 AbstractLinkedProcessorSlot 的字段，正是这个字段将 ProcessorSlot 串成一条单向链表。AbstractLinkedProcessorSlot 部分源码如下。

public abstract class AbstractLinkedProcessorSlot<T> implements ProcessorSlot<T> {
    // 当前节点的下一个节点
    private AbstractLinkedProcessorSlot<?> next = null;

    public void setNext(AbstractLinkedProcessorSlot<?> next) {
        this.next = next;
    }
}



实现责任链调用是由前一个 AbstractLinkedProcessorSlot 调用 fireEntry 方法或者 fireExit 方法，在 fireEntry 与 fireExit 方法中调用下一个 AbstractLinkedProcessorSlot（next）的 entry 方法或 exit 方法。AbstractLinkedProcessorSlot 的 fireEntry 与 fireExit 方法的实现源码如下：

public abstract class AbstractLinkedProcessorSlot<T> implements ProcessorSlot<T> {
    // 当前节点的下一个节点
    private AbstractLinkedProcessorSlot<?> next = null;

    public void setNext(AbstractLinkedProcessorSlot<?> next) {
        this.next = next;
    }  

    @Override
    public void fireEntry(Context context, ResourceWrapper resourceWrapper, Object obj, int count, boolean prioritized, Object... args)
        throws Throwable {
        if (next != null) {
            T t = (T) obj; 
            // 调用下一个 ProcessorSlot 的 entry 方法
            next.entry(context,resourceWrapper,t,count,prioritized,args);
        }
    }

    @Override
    public void fireExit(Context context, ResourceWrapper resourceWrapper, int count, Object... args) {
        if (next != null) {
            // 调用下一个 ProcessorSlot 的 exit 方法
            next.exit(context, resourceWrapper, count, args);
        }
    }
}



ProcessorSlotChain 也继承 AbstractLinkedProcessorSlot，只不过加了两个方法：提供将一个 ProcessorSlot 添加到链表的头节点的 addFirst 方法，以及提供将一个 ProcessorSlot 添加到链表末尾的 addLast 方法。

ProcessorSlotChain 的默认实现类是 DefaultProcessorSlotChain，DefaultProcessorSlotChain 有一个指向链表头节点的 first 字段和一个指向链表尾节点的 end 字段，头节点字段是一个空实现的 AbstractLinkedProcessorSlot。DefaultProcessorSlotChain 源码如下。

public class DefaultProcessorSlotChain extends ProcessorSlotChain {
     // first，指向链表头节点
    AbstractLinkedProcessorSlot<?> first = new AbstractLinkedProcessorSlot<Object>() {

        @Override
        public void entry(Context context, ResourceWrapper resourceWrapper, Object t, int count, boolean prioritized, Object... args)
            throws Throwable {
            super.fireEntry(context, resourceWrapper, t, count, prioritized, args);
        }

        @Override
        public void exit(Context context, ResourceWrapper resourceWrapper, int count, Object... args) {
            super.fireExit(context, resourceWrapper, count, args);
        }

    };
    // end，指向链表尾节点
    AbstractLinkedProcessorSlot<?> end = first;

    @Override
    public void addFirst(AbstractLinkedProcessorSlot<?> protocolProcessor) {
        protocolProcessor.setNext(first.getNext());
        first.setNext(protocolProcessor);
        if (end == first) {
            end = protocolProcessor;
        }
    }

    @Override
    public void addLast(AbstractLinkedProcessorSlot<?> protocolProcessor) {
        end.setNext(protocolProcessor);
        end = protocolProcessor;
    }

   // 调用头节点的 entry 方法
    @Override
    public void entry(Context context, ResourceWrapper resourceWrapper, Object obj, int count, boolean prioritized, Object... args)
        throws Throwable {
        T t = (T) obj;
        first.entry(context, resourceWrapper, t, count, prioritized, args);
    }
    // 调用头节点的 exit 方法
    @Override
    public void exit(Context context, ResourceWrapper resourceWrapper, int count, Object... args) {
        first.exit(context, resourceWrapper, count, args);
    }

}



Sentinel 中的责任链模式

责任链模式是非常常用的一种设计模式。在 Shiro 框架中，实现资源访问权限过滤的骨架（过滤器链）使用的是责任链模式；在 Netty 框架中，使用责任链模式将处理请求的 ChannelHandler 包装为链表，实现局部串行处理请求。

Sentinel 的责任链实现上与 Netty 有相似的地方，Sentinel 的 ProcessorSlot#entry 方法与 Netty 的实现一样，都是按节点在链表中的顺序被调用，区别在于 Sentinel 的 ProcessorSlot#exit 方法并不像 Netty 那样是从后往前调用的。且与 Netty 不同的是，Netty 的 ChannelHandler 是线程安全的，也就是局部串行，由于 Sentinel 是与资源为维度的，所以必然实现不了局部串行。

Sentinel 会为每个资源创建且仅创建一个 ProcessorSlotChain，只要名称相同就认为是同一个资源。ProcessorSlotChain 被缓存在 CtSph.chainMap 静态字段，key 为资源 ID，每个资源的 ProcessorSlotChain 在 CtSph#entryWithPriority 方法中创建，代码如下。

public class CtSph implements Sph {
    // 资源与 ProcessorSlotChain 的映射
    private static volatile Map<ResourceWrapper, ProcessorSlotChain> chainMap
        = new HashMap<ResourceWrapper, ProcessorSlotChain>();

   private Entry entryWithPriority(ResourceWrapper resourceWrapper, int count, boolean prioritized, Object... args)
        throws BlockException {
        Context context = ContextUtil.getContext();
        //......
        // 开始构造 Chain
        ProcessorSlot<Object> chain = lookProcessChain(resourceWrapper);
        //......
        Entry e = new CtEntry(resourceWrapper, chain, context);
        try {
            chain.entry(context, resourceWrapper, null, count, prioritized, args);
        } catch (BlockException e1) {
            e.exit(count, args);
            throw e1;
        }
        return e;
    }
}



Sentinel 的整体工作流程

如果不借助 Sentinel 提供的适配器，我们可以这样使用 Sentinel。

ContextUtil.enter("上下文名称，例如：sentinel_spring_web_context");
Entry entry = null;
try {
     entry = SphU.entry("资源名称，例如：/rpc/openfein/demo", EntryType.IN (或者 EntryType.OUT));
     // 执行业务方法
       return doBusiness();
} catch (Exception e) {
     if (!(e instanceof BlockException)) {
          Tracer.trace(e);
     }
     throw e;
} finally {
     if (entry != null) {
         entry.exit(1);
     }
     ContextUtil.exit();
}



上面代码我们分为五步分析：


调用 ContextUtil#enter 方法；
调用 SphU#entry 方法；
如果抛出异常，且异常类型非 BlockException 异常，则调用 Tracer#trace 方法记录异常；
调用 Entry#exit 方法；
调用 ContextUtil#exit 方法。


调用 ContextUtil#enter 方法

ContextUtil#enter 方法负责为当前调用链路创建 Context，以及为 Conetxt 创建 EntranceNode，源码如下。

public static Context enter(String name, String origin) {
        return trueEnter(name, origin);
    }

    protected static Context trueEnter(String name, String origin) {
        Context context = contextHolder.get();
        if (context == null) {
            Map<String, DefaultNode> localCacheNameMap = contextNameNodeMap;
            DefaultNode node = localCacheNameMap.get(name);
            if (node == null) {
               //....
                    try {
                        LOCK.lock();
                        node = contextNameNodeMap.get(name);
                        if (node == null) {
                            //....
                                node = new EntranceNode(new StringResourceWrapper(name, EntryType.IN), null);
                                // Add entrance node.
                                Constants.ROOT.addChild(node);
                                Map<String, DefaultNode> newMap = new HashMap<>(contextNameNodeMap.size() + 1);
                                newMap.putAll(contextNameNodeMap);
                                newMap.put(name, node);
                                contextNameNodeMap = newMap;
                        }
                    } finally {
                        LOCK.unlock();
                    }
                }
            }
            context = new Context(node, name);
            context.setOrigin(origin);
            contextHolder.set(context);
        }
        return context;
    }



ContextUtil 使用 ThreadLocal 存储当前调用链路的 Context，例如 Web MVC 应用中使用 Sentinel 的 Spring MVC 适配器，在接收到请求时，调用 ContextUtil#enter 方法会创建一个名为“sentinel_spring_web_context”的 Context，并且如果是首次创建还会为所有名为“sentinel_spring_web_context”的 Context 创建一个 EntranceNode。

Context 是每个线程只创建一个，而 EntranceNode 则是每个 Context.name 对应创建一个。也就是说，应用每接收一个请求都会创建一个新的 Context，但名称都是 sentinel_spring_web_context，而且都是使用同一个 EntranceNode，这个 EntranceNode 将会存储所有接口的 DefaultNode，同时这个 EntranceNode 也是 Constants.ROOT 的子节点。

调用 SphU#entry 方法

Sentinel 的核心骨架是 ProcessorSlotChain，所以核心的流程是一次 SphU#entry 方法的调用以及一次 CtEntry#exit 方法的调用。

SphU#entry 方法调用 CtSph#entry 方法，CtSph 负责为资源创建 ResourceWrapper 对象并为资源构造一个全局唯一的 ProcessorSlotChain、为资源创建 CtEntry 并将 CtEntry 赋值给当前调用链路的 Context.curEntry、最后调用 ProcessorSlotChain#entry 方法完成一次单向链表的 entry 方法调用。

ProcessorSlotChain 的一次 entry 方法的调用过程如下图所示。



调用 Tracer 的 trace 方法

只在抛出非 BlockException 异常时才会调用 Tracer#trace 方法，用于记录当前资源调用异常，为当前资源的 DefaultNode 自增异常数。

public class Tracer {
    // 调用 Tracer 的 trace 方法最终会调用到这个方法
    private static void traceExceptionToNode(Throwable t, int count, Entry entry, DefaultNode curNode) {
        if (curNode == null) {
            return;
        }
        // .....
        // clusterNode can be null when Constants.ON is false.
        ClusterNode clusterNode = curNode.getClusterNode();
        if (clusterNode == null) {
            return;
        }
        clusterNode.trace(t, count);
    }
}



如上代码所示，traceExceptionToNode 方法中首先获取当前资源的 ClusterNode，然后调用 ClusterNode#trace 方法记录异常。因为一个资源只创建一个 ProcessorSlotChain，一个 ProcessorSlotChain 只创建 ClusterBuilderSlot，一个 ClusterBuilderSlot 只创建一个 ClusterNode，所以一个资源对应一个 ClusterNode，这个 ClusterNode 就是用来统计一个资源的全局指标数据的，熔断降级与限流降级都有使用到这个 ClusterNode。

ClusterNode#trace 方法的实现如下：

   public void trace(Throwable throwable, int count) {
        if (count <= 0) {
            return;
        }
        if (!BlockException.isBlockException(throwable)) {
            // 非 BlockException 异常，自增异常总数
            this.increaseExceptionQps(count);
        }
    }



调用 Entry#exit 方法

下面是 CtEntry#exit 方法的实现，为了简短且易于理解，下面给出的 exitForContext 方法的源码有删减。

    @Override
    public void exit(int count, Object... args) throws ErrorEntryFreeException {
        trueExit(count, args);
    }
    @Override
    protected Entry trueExit(int count, Object... args) throws ErrorEntryFreeException {
        exitForContext(context, count, args);
        return parent;
    }
    protected void exitForContext(Context context, int count, Object... args) throws ErrorEntryFreeException {
        if (context != null) {
                //......
                // 1、调用 ProcessorSlotChain 的 exit 方法
                if (chain != null) {
                    chain.exit(context, resourceWrapper, count, args);
                }
                // 2、将当前 CtEntry 的父节点设置为 Context 的当前节点
                context.setCurEntry(parent);
                if (parent != null) {
                    ((CtEntry)parent).child = null;
                }
                // .....
        }
    }



CtSph 在创建 CtEntry 时，将资源的 ProcessorSlotChain 赋值给了 CtEntry，所以在调用 CtEntry#exit 方法时，CtEntry 能够拿到当前资源的 ProcessorSlotChain，并调用 ProcessorSlotChain 的 exit 方法完成一次单向链表的 exit 方法调用。其过程与 ProcessorSlotChain 的一次 entry 方法的调用过程一样，因此不做分析。

CtEntry 在退出时还会还原 Context.curEntry。上一篇介绍 CtEntry 时说到，CtEntry 用于维护父子 Entry，每一次调用 SphU#entry 都会创建一个 CtEntry，如果应用处理一次请求的路径上会多次调用 SphU#entry，那么这些 CtEntry 会构成一个双向链表。在每次创建 CtEntry，都会将 Context.curEntry 设置为这个新的 CtEntry，双向链表的作用就是在调用 CtEntry#exit 方法时，能够将 Context.curEntry 还原为上一个 CtEntry。

调用 ContextUtil 的 exit 方法

ContextUtil#exit 方法就简单了，其代码如下：

   public static void exit() {
        Context context = contextHolder.get();
        if (context != null && context.getCurEntry() == null) {
            contextHolder.set(null);
        }
    }



如果 Context.curEntry 为空，则说明所有 SphU#entry 都对应执行了一次 Entry#exit 方法，此时就可以将 Context 从 ThreadLocal 中移除。

                        
                        
                            