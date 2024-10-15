
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            21  指标定制：如何实现自定义度量指标和 Actuator 端点？
                            20 讲中我们引入了 Spring Boot Actuator 组件来满足 Spring Boot 应用程序的系统监控功能，并重点介绍了如何扩展常见的 Info 和 Health 监控端点的实现方法。

这一讲我们继续讨论如何扩展 Actuator 端点，但更多关注与度量指标相关的内容。同时，我们还将给出如何创建自定义 Actuator 的实现方法，以便应对默认端点无法满足需求的应用场景。

Actuator 中的度量指标

对于系统监控而言，度量是一个很重要的维度。 在 Spring Boot 2.X 版本中，Actuator 组件主要使用内置的 Micrometer 库实现度量指标的收集和分析。

Micrometer 度量库

Micrometer 是一款监控指标的度量类库，为 Java 平台上的性能数据收集提供了一套通用的 API。在应用程序中，我们只使用 Micrometer 提供的通用 API 即可收集度量指标。

下面我们先来简要介绍 Micrometer 中包含的几个核心概念。

首先我们需要介绍的是计量器 Meter，它是一个接口，代表的是需要收集的性能指标数据。关于 Meter 的定义如下：

public interface Meter extends AutoCloseable {

   

    //Meter 的唯一标识，是名称和标签的一种组合

    Id getId();

 

    //一组测量结果

	Iterable<Measurement> measure();

	 

	//Meter 的类型枚举值

    enum Type {

        COUNTER,

        GAUGE,

        LONG_TASK_TIMER,

        TIMER,

        DISTRIBUTION_SUMMARY,

        OTHER

    }

}


通过上述代码，我们注意到 Meter 中存在一个 Id 对象，该对象的作用是定义 Meter 的名称和标签。从 Type 的枚举值中，我们不难看出 Micrometer 中包含的所有计量器类型。

接下来我们先说明两个概念。


Meter 的名称：对于计量器来说，每个计量器都有自己的名称，而且在创建时它们都可以指定一系列标签。
Meter 的标签：标签的作用在于监控系统可以通过这些标签对度量进行分类过滤。


在日常开发过程中，常用的计量器类型主要分为计数器 Counter、计量仪 Gauge 和计时器 Timer 这三种。


Counter：这个计量器的作用和它的名称一样，就是一个不断递增的累加器，我们可以通过它的 increment 方法实现累加逻辑。
Gauge：与 Counter 不同，Gauge 所度量的值并不一定是累加的，我们可以通过它的 gauge 方法指定数值。
Timer：这个计量器比较简单，就是用来记录事件的持续时间。


既然我们已经明确了常用的计量器及其使用场景，那么如何创建这些计量器呢？

在 Micrometer 中，我们提供了一个计量器注册表 MeterRegistry，它主要负责创建和维护各种计量器。关于 MeterRegistry 的创建方法如下代码所示：

public abstract class MeterRegistry implements AutoCloseable {

 

    protected abstract <T> Gauge newGauge(Meter.Id id, @Nullable T obj, ToDoubleFunction<T> valueFunction);

 

    protected abstract Counter newCounter(Meter.Id id);

 

    protected abstract Timer newTimer(Meter.Id id, DistributionStatisticConfig distributionStatisticConfig, PauseDetector pauseDetector);

…

}


以上代码只是创建 Meter 的一种途径，从中我们可以看到 MeterRegistry 针对不同的 Meter 提供了对应的创建方法。

而创建 Meter 的另一种途径是使用某个 Meter 的具体 builder 方法。以 Counter 为例，它的定义中包含了一个 builder 方法和一个 register 方法，如下代码所示：

public interface Counter extends Meter {

    static Builder builder(String name) {

        return new Builder(name);

	}

	 

    default void increment() {

        increment(1.0);

	}

	 

	void increment(double amount);

	 

	double count();

 

    @Override

    default Iterable<Measurement> measure() {

        return Collections.singletonList(new Measurement(this::count, Statistic.COUNT));

	}

         …

 

    public Counter register(MeterRegistry registry) {

        return registry.counter(new Meter.Id(name, tags, baseUnit, description, Type.COUNTER));

    }

}


注意到最后的 register 方法就是将当前的 Counter 注册到 MeterRegistry 中，因此我们需要创建一个 Counter。通常，我们会采用如下所示代码进行创建。

Counter counter = Counter.builder("counter1")

        .tag("tag1", "value1")

        .register(registry);


了解了 Micrometer 框架的基本概念后，接下来我们回到 Spring Boot Actuator，一起来看看它提供的专门针对度量指标管理的 Metrics 端点。

扩展 Metrics 端点

在 Spring Boot 中，它为我们提供了一个 Metrics 端点用于实现生产级的度量工具。访问 actuator/metrics 端点后，我们将得到如下所示的一系列度量指标。

{

     "names":[

         "jvm.memory.max",

         "jvm.threads.states",

         "jdbc.connections.active",

         "jvm.gc.memory.promoted",

         "jvm.memory.used",

         "jvm.gc.max.data.size",

         "jdbc.connections.max",

         "jdbc.connections.min",

         "jvm.memory.committed",

         "system.cpu.count",

         "logback.events",

         "http.server.requests",

         "jvm.buffer.memory.used",

         "tomcat.sessions.created",

         "jvm.threads.daemon",

         "system.cpu.usage",

         "jvm.gc.memory.allocated",

         "hikaricp.connections.idle",

         "hikaricp.connections.pending",

         "jdbc.connections.idle",

         "tomcat.sessions.expired",

         "hikaricp.connections",

         "jvm.threads.live",

         "jvm.threads.peak",

         "hikaricp.connections.active",

         "hikaricp.connections.creation",

         "process.uptime",

         "tomcat.sessions.rejected",

         "process.cpu.usage",

         "jvm.classes.loaded",

         "hikaricp.connections.max",

         "hikaricp.connections.min",

         "jvm.gc.pause",

         "jvm.classes.unloaded",

         "tomcat.sessions.active.current",

         "tomcat.sessions.alive.max",

         "jvm.gc.live.data.size",

         "hikaricp.connections.usage",

         "hikaricp.connections.timeout",

         "jvm.buffer.count",

         "jvm.buffer.total.capacity",

         "tomcat.sessions.active.max",

         "hikaricp.connections.acquire",

         "process.start.time"

     ]

 }


以上代码中涉及的指标包括常规的系统内存总量、空闲内存数量、处理器数量、系统正常运行时间、堆信息等，也包含我们引入 JDBC 和 HikariCP 数据源组件之后的数据库连接信息等。此时，如果我们想了解某项指标的详细信息，在 actuator/metrics 端点后添加对应指标的名称即可。

例如我们想了解当前内存的使用情况，就可以通过 actuator/metrics/jvm.memory.used 端点进行获取，如下代码所示。

{

     "name":"jvm.memory.used",

     "description":"The amount of used memory",

     "baseUnit":"bytes",

     "measurements":[

         {

             "statistic":"VALUE",

             "value":115520544

         }

     ],

     "availableTags":[

         {

             "tag":"area",

             "values":[

                 "heap",

                 "nonheap"

             ]

         },

         {

             "tag":"id",

             "values":[

                 "Compressed Class Space",

                 "PS Survivor Space",

                 "PS Old Gen",

                 "Metaspace",

                 "PS Eden Space",

                 "Code Cache"

             ]

         }

     ]

 }


前面介绍 Micrometer 时，我们已经提到 Metrics 指标体系中包含支持 Counter 和 Gauge 这两种级别的度量指标。通过将 Counter 或 Gauge 注入业务代码中，我们就可以记录自己想要的度量指标。其中，Counter 用来暴露 increment() 方法，而 Gauge 用来提供一个 value() 方法。

下面我们以 Counter 为例介绍在业务代码中嵌入自定义 Metrics 指标的方法，如下代码所示：

@Component

public class CounterService {

    

    public CounterService() {

        Metrics.addRegistry(new SimpleMeterRegistry());

    }

 

    public void counter(String name, String... tags) {

        Counter counter = Metrics.counter(name, tags);

        counter.increment();

    }

}


在这段代码中，我们构建了一个公共服务 CounterService，并开放了一个 Counter 方法供业务系统进行使用。当然，你也可以自己实现类似的工具类完成对各种计量器的封装。

另外，Micrometer 还提供了一个 MeterRegistry 工具类供我们创建度量指标。因此，我们也十分推荐使用 MeterRegistry 对各种自定义度量指标的创建过程进行简化。

使用 MeterRegistry

再次回到 SpringCSS 案例，此次我们来到 customer-service 的 CustomerTicketService 中。

比如我们希望系统每创建一个客服工单，就对所创建的工单进行计数，并作为系统运行时的一项度量指标，该效果的实现方式如下代码所示：

@Service

public class CustomerTicketService {

    

    @Autowired

    private MeterRegistry meterRegistry;

 

public CustomerTicket generateCustomerTicket(Long accountId, String orderNumber) {

 

    CustomerTicket customerTicket = new CustomerTicket();

    …

        meterRegistry.summary("customerTickets.generated.count").record(1);

 

        return customerTicket;

    }   

}


在上述 generateCustomerTicket 方法中，通过 MeterRegistry 我们实现了每次创建 CustomerTicket 时自动添加一个计数的功能。

而且，MeterRegistry 还提供了一些类工具方法用于创建自定义度量指标。这些类工具方法除了常规的 counter、gauge、timer 等对应具体 Meter 的工具方法之外，还包括上述代码中的 summary 方法，且 Summary 方法返回的是一个 DistributionSummary 对象，关于它的定义如下代码所示：

public interface DistributionSummary extends Meter, HistogramSupport {

 

    static Builder builder(String name) {

        return new Builder(name);

    }

    //记录数据

    void record(double amount);

 

    //记录操作执行的次数

    long count();

 

    //记录数据的数量

    double totalAmount();

 

    //记录数据的平均值

    default double mean() {

        return count() == 0 ? 0 : totalAmount() / count();

    }

 

    //记录数据的最大值

	double max();

	…

}


因为 DistributionSummary 的作用是记录一系列的事件并对这些事件进行处理，所以在 CustomerTicketService 中添加的meterRegistry.summary(“customertickets.generated.count”).record(1) 这行代码相当于每次调用 generateCustomerTicket 方法时，我们都会对这次调用进行记录。

现在访问 actuator/metrics/customertickets.generated.count 端点，我们就能看到如下所示的随着服务调用不断递增的度量信息。

{

     "name":"customertickets.generated.count",

     "measurements":[

         {

             "statistic":"Count",

             "value":1

         },

         {

             "statistic":"Total",

             "value":19

         }

     ] 

 }


显然，通过 MeterRegistry 实现自定义度量指标的使用方法更加简单。这里，你也可以结合业务需求尝试该类的不同功能。

接下来我们再来看一个相对比较复杂的使用方式。在 customer-service 中，我们同样希望系统存在一个度量值，该度量值用于记录所有新增的 CustomerTicket 个数，这次的示例代码如下所示：

@Component

public class CustomerTicketMetrics extends AbstractRepositoryEventListener<CustomerTicket> {

 

    private MeterRegistry meterRegistry;

 

    public CustomerTicketMetrics(MeterRegistry meterRegistry) {

        this.meterRegistry = meterRegistry;

    }

 

    @Override

    protected void onAfterCreate(CustomerTicket customerTicket) {                 meterRegistry.counter("customerTicket.created.count").increment();  

	}

}


首先，这里我们使用了 MeterRegistry 的 Counter 方法初始化一个 counter，然后调用它的 increment 方法增加度量计数（这部分内容我们已经很熟悉了）。

注意到这里，我们同时还引入了一个 AbstractRepositoryEventListener 抽象类，这个抽象类能够监控 Spring Data 中 Repository 层操作所触发的事件 RepositoryEvent，例如实体创建前后的 BeforeCreateEvent 和 AfterCreateEvent 事件、实体保存前后的 BeforeSaveEvent 和 AfterSaveEvent 事件等。

针对这些事件，AbstractRepositoryEventListener 能捕捉并调用对应的回调函数。关于 AbstractRepositoryEventListener 类的部分实现如下代码所示：

public abstract class AbstractRepositoryEventListener<T> implements ApplicationListener<RepositoryEvent> {

 

    public final void onApplicationEvent(RepositoryEvent event) {

        

        …

        Class<?> srcType = event.getSource().getClass();

        

        if (event instanceof BeforeSaveEvent) {

            onBeforeSave((T) event.getSource());

        } else if (event instanceof BeforeCreateEvent) {

            onBeforeCreate((T) event.getSource());

        } else if (event instanceof AfterCreateEvent) {

            onAfterCreate((T) event.getSource());

        } else if (event instanceof AfterSaveEvent) {

            onAfterSave((T) event.getSource());

              }

        …

	}

}


在这段代码中，我们可以看到 AbstractRepositoryEventListener 直接实现了 Spring 容器中的 ApplicationListener 监听器接口，并在 onApplicationEvent 方法中根据所传入的事件类型触发了回调函数。

以案例中的需求场景为例，我们可以在创建 Account 实体之后执行度量操作。也就是说，可以把度量操作的代码放在 onAfterCreate 回调函数中，正如案例代码中所展示那样。

现在我们执行生成客户工单操作，并访问对应的 Actuator 端点，同样可以看到度量数据在不断上升。

自定义 Actuator 端点

在日常开发过程中，扩展现有端点有时并不一定能满足业务需求，而自定义 Spring Boot Actuator 监控端点算是一种更灵活的方法。

假设我们需要提供一个监控端点以获取当前系统的用户信息和计算机名称，就可以通过一个独立的 MySystemEndPoint 进行实现，如下代码所示：

@Configuration

@Endpoint(id = "mysystem", enableByDefault=true)

public class MySystemEndpoint { 

 

    @ReadOperation

    public Map<String, Object> getMySystemInfo() {

        Map<String,Object> result= new HashMap<>();

        Map<String, String> map = System.getenv();

        result.put("username",map.get("USERNAME"));

        result.put("computername",map.get("COMPUTERNAME"));

        return result;

    }

}


在这段代码中我们可以看到，MySystemEndpoint 主要通过系统环境变量获取所需监控信息。

注意，这里我们引入了一个新的注解 @Endpoint，该注解定义如下代码所示：

@Target(ElementType.TYPE)

@Retention(RetentionPolicy.RUNTIME)

@Documented

public @interface Endpoint {

 

    //端点 id

    String id() default "";

 

    //是否默认启动标志位

    boolean enableByDefault() default true;

}


这段代码中的 @Endpoint 注解主要用于设置端点 id 及是否默认启动的标志位。且在案例中，我们指定了 id 为“mysystem”，enableByDefault 标志为 true。

事实上，在 Actuator 中也存在一批类似 @Endpoint 的端点注解。其中被 @Endpoint 注解的端点可以通过 JMX 和 Web 访问应用程序，对应的被 @JmxEndpoint 注解的端点只能通过 JMX 访问，而被 @WebEndpoint 注解的端点只能通过 Web 访问。

在示例代码中，我们还看到了一个 @ReadOperation 注解，该注解作用于方法，用于标识读取数据操作。在 Actuator 中，除了提供 @ReadOperation 注解之外，还提供 @WriteOperation 和 @DeleteOperation 注解，它们分别对应写入操作和删除操作。

现在，通过访问 http://localhost:8080/actuator/mysystem，我们就能获取如下所示监控信息。

{

     "computername":"LAPTOP-EQB59J5P",

     "username":"user"

 }


有时为了获取特定的度量信息，我们需要对某个端点传递参数，而 Actuator 专门提供了一个 @Selector 注解标识输入参数，示例代码如下所示：

@Configuration

@Endpoint(id = "account", enableByDefault = true)

public class AccountEndpoint {

    

    @Autowired

    private AccountRepository accountRepository;    

 

    @ReadOperation

    public Map<String, Object> getMySystemInfo(@Selector String arg0) {

        Map<String, Object> result = new HashMap<>();

        result.put(accountName, accountRepository.findAccountByAccountName(arg0));

        return result;

    }

}


这段代码的逻辑非常简单，就是根据传入的 accountName 获取用户账户信息。

这里请注意，通过 @Selector 注解，我们就可以使用http://localhost:8080/actuator/ account/account1 这样的端口地址触发度量操作了。

小结与预告

度量是我们观测一个应用程序运行时状态的核心手段。这一讲我们介绍了 Spring Boot 中新引入的 Micrometer 度量库，以及该库中提供的各种度量组件。同时，我们还基于 Micrometer 中的核心工具类 MeterRegistry 完成了在业务系统中嵌入度量指标的实现过程。最后，我们还简要介绍了如何自定义一个 Actuator 端点的开发方法。

                        
                        
                            