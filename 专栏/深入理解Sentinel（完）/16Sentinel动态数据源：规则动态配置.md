
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            16 Sentinel 动态数据源：规则动态配置
                            经过前面的学习，我们知道，为资源配置各种规则可使用 Sentinel 提供的各种规则对应的 loadRules API，但这种以编码的方式配置规则很难实现动态修改。但基于 Sentinel 提供的各种规则对应的 loadRules API，我们可以自己实现规则的动态更新，而这一功能几乎在每个需要使用 Sentinel 的微服务项目中都需要实现一遍。Sentinel 也考虑到了这点，所以提供了动态数据源接口，并且提供了多种动态数据源的实现，尽管我们可能不会用到。

动态数据源作为扩展功能放在 sentinel-extension 模块下，前面我们学习的热点参数限流模块 sentinel-parameter-flow-control 也是在该模块下。在 1.7.1 版本，sentinel-extension 模块下的子模块除 sentinel-parameter-flow-control、sentinel-annotation-aspectj 之外，其余子模块都是实现动态数据源的模块。


sentinel-datasource-extension：定义动态数据源接口、提供抽象类
sentinel-datasource-redis：基于 Redis 实现的动态数据源
sentinel-datasource-zookeeper： 基于 ZooKeeper 实现的动态数据源
其它省略


显然，sentinel-datasource-extension 模块才是我们主要研究的模块，这是 Sentinel 实现动态数据源的核心。

SentinelProperty

SentinelProperty 是 Sentinel 提供的一个接口，可注册到 Sentinel 提供的各种规则的 Manager，例如 FlowRuleManager，并且可以给 SentinelProperty 添加监听器，在配置改变时，你可以调用 SentinelProperty#updateValue 方法，由它负责调用监听器去更新规则，而不需要调用 FlowRuleManager#loadRules 方法。同时，你也可以注册额外的监听器，在配置改变时做些别的事情。

SentinelProperty 并非 sentinel-datasource-extension 模块中定义的接口，而是 sentinel-core 定义的接口，其源码如下：

public interface SentinelProperty<T> {
    void addListener(PropertyListener<T> listener);
    void removeListener(PropertyListener<T> listener);
    boolean updateValue(T newValue);
}




addListener：添加监听器
removeListener：移除监听器
updateValue：通知所有监听器配置更新，参数 newValue 为新的配置


默认使用的实现类是 DynamicSentinelProperty，其实现源码如下（有删减）：

public class DynamicSentinelProperty<T> implements SentinelProperty<T> {
    // 存储注册的监听器
    protected Set<PropertyListener<T>> listeners = Collections.synchronizedSet(new HashSet<PropertyListener<T>>());
    @Override
    public void addListener(PropertyListener<T> listener) {
        listeners.add(listener);
        listener.configLoad(value);
    }
    @Override
    public void removeListener(PropertyListener<T> listener) {
        listeners.remove(listener);
    }
    @Override
    public boolean updateValue(T newValue) {
        for (PropertyListener<T> listener : listeners) {
            listener.configUpdate(newValue);
        }
        return true;
    }
}



可见，DynamicSentinelProperty 使用 Set 存储已注册的监听器，updateValue 负责通知所有监听器，调用监听器的 configUpdate 方法。

在前面分析 FlowRuleManager 时，我们只关注了其 loadRules 方法，除了使用 loadRules 方法加载规则配置之外，FlowRuleManager 还提供 registerProperty API，用于注册 SentinelProperty。

使用 SentinelProperty 实现加载 FlowRule 的步骤如下：


给 FlowRuleManager 注册一个 SentinelProperty，替换 FlowRuleManager 默认创建的 SentinelProperty（因为默认的 SentinelProperty 外部拿不到）；
这一步由 FlowRuleManager 完成，FlowRuleManager 会给 SentinelProperty 注册 FlowPropertyListener 监听器，该监听器负责更新 FlowRuleManager.flowRules 缓存的限流规则；
在应用启动或者规则配置改变时，只需要调用 SentinelProperty#updateValue 方法，由 updateValue 通知 FlowPropertyListener 监听器去更新规则。


FlowRuleManager 支持使用 SentinelProperty 加载或更新限流规则的实现源码如下：

public class FlowRuleManager {
    // 缓存限流规则
    private static final Map<String, List<FlowRule>> flowRules = new ConcurrentHashMap<String, List<FlowRule>>();
    // PropertyListener 监听器
    private static final FlowPropertyListener LISTENER = new FlowPropertyListener();
    // SentinelProperty
    private static SentinelProperty<List<FlowRule>> currentProperty 
       // 提供默认的 SentinelProperty
       = new DynamicSentinelProperty<List<FlowRule>>();

  static {
       // 给默认的 SentinelProperty 注册监听器（FlowPropertyListener）
        currentProperty.addListener(LISTENER);
  }

  // 注册 SentinelProperty
  public static void register2Property(SentinelProperty<List<FlowRule>> property) {
        synchronized (LISTENER) {
            currentProperty.removeListener(LISTENER);
            // 注册监听器
            property.addListener(LISTENER);
            currentProperty = property;
        }
  }
}



实现更新限流规则缓存的 FlowPropertyListener 是 FlowRuleManager 的一个内部类，其源码如下：

private static final class FlowPropertyListener implements PropertyListener<List<FlowRule>> {
        @Override
        public void configUpdate(List<FlowRule> value) {
            Map<String, List<FlowRule>> rules = FlowRuleUtil.buildFlowRuleMap(value);
            if (rules != null) {
                // 先清空缓存再写入
                flowRules.clear();
                flowRules.putAll(rules);
            }
        }
        @Override
        public void configLoad(List<FlowRule> conf) {
            Map<String, List<FlowRule>> rules = FlowRuleUtil.buildFlowRuleMap(conf);
            if (rules != null) {
                flowRules.clear();
                flowRules.putAll(rules);
            }
        }
}



PropertyListener 接口定义的两个方法：


configUpdate：在规则更新时被调用，被调用的时机就是在 SentinelProperty#updateValue 方法被调用时。
configLoad：在规则首次加载时被调用，是否会被调用由 SentinelProperty 决定。DynamicSentinelProperty 就没有调用这个方法。


所以，现在我们有两种方法更新限流规则：


调用 FlowRuleManager#loadRules 方法
注册 SentinelProperty，调用 SentinelProperty#updateValue 方法


ReadableDataSource

Sentinel 将读和写数据源抽离成两个接口，一开始只有读接口，写接口是后面才加的功能，目前来看，写接口只在热点参数限流模块中使用到。事实上，使用读接口就已经满足我们的需求。ReadableDataSource 接口的定义如下：

public interface ReadableDataSource<S, T> {
    T loadConfig() throws Exception;
    S readSource() throws Exception;
    SentinelProperty<T> getProperty();
    void close() throws Exception;
}



ReadableDataSource 是一个泛型接口，参数化类型 S 代表用于装载从数据源读取的配置的类型，参数化类型 T 代表对应 Sentinel 中的规则类型。例如，我们可以定义一个 FlowRuleProps 类，用于装载从 yml 配置文件中读取的限流规则配置，然后再将 FlowRuleProps 转为 FlowRule，所以 S 可以替换为 FlowRuleProps，T 可以替换为 List<FlowRule>。

ReadableDataSource 接口定义的方法解释说明如下：


loadConfig：加载配置。
readSource：从数据源读取配置，数据源可以是 yaml 配置文件，可以是 MySQL、Redis 等，由实现类决定从哪种数据源读取配置。
getProperty：获取 SentinelProperty。
close：用于关闭数据源，例如使用文件存储配置时，可在此方法实现关闭文件输入流等。


如果动态数据源提供 SentinelProperty，则可以调用 getProperty 方法获取动态数据源的 SentinelProperty，将 SentinelProperty 注册给规则管理器（XxxManager），动态数据源在读取到配置时就可以调用自身 SentinelProperty 的 updateValue 方法通知规则管理器（XxxManager）更新规则。

AbstractDataSource 是一个抽象类，该类实现 ReadableDataSource 接口，用于简化具体动态数据源的实现，子类只需要继承 AbstractDataSource 并实现 readSource 方法即可。AbstractDataSource 源码如下：

public abstract class AbstractDataSource<S, T> implements ReadableDataSource<S, T> {
    protected final Converter<S, T> parser;
    protected final SentinelProperty<T> property;

    public AbstractDataSource(Converter<S, T> parser) {
        if (parser == null) {
            throw new IllegalArgumentException("parser can't be null");
        }
        this.parser = parser;
        this.property = new DynamicSentinelProperty<T>();
    }

    @Override
    public T loadConfig() throws Exception {
        return loadConfig(readSource());
    }

    public T loadConfig(S conf) throws Exception {
        T value = parser.convert(conf);
        return value;
    }

    @Override
    public SentinelProperty<T> getProperty() {
        return property;
    }
}



从源码可以看出：


AbstractDataSource 要求所有子类都必须提供一个数据转换器（Converter），Converter 用于将 S 类型的对象转为 T 类型对象，例如将 FlowRuleProps 对象转为 FlowRule 集合。
AbstractDataSource 在构造方法中创建 DynamicSentinelProperty，因此子类无需创建 SentinelProperty。
AbstractDataSource 实现 loadConfig 方法，首先调用子类实现的 readSource 方法从数据源读取配置，返回的对象类型为 S，再调用 Converter#convert 方法，将对象类型由 S 转为 T。


Converter 接口的定义如下：

public interface Converter<S, T> {
    T convert(S source);
}




convert：将类型为 S 的参数 source 转为类型为 T 的对象。


基于 Spring Cloud 动态配置实现规则动态配置

我们项目中并未使用 Sentinel 提供的任何一种动态数据源的实现，而是选择自己实现数据源，因为我们项目是部署在 Kubernetes 集群上的，我们可以利用 ConfigMap 资源存储限流、熔断降级等规则。Spring Cloud Kubernetes 提供了 Spring Cloud 动态配置接口的实现，因此，我们不需要关心怎么去读取 ConfigMap 资源。就相当于基于 Spring Cloud 动态配置实现 Sentinel 规则动态数据源。Spring Cloud 动态配置如何使用这里不做介绍。

以实现 FlowRule 动态配置为例，其步骤如下。

第一步：定义一个用于装载动态配置的类，如 FlowRuleProps 所示。

@Component
@RefreshScope
@ConfigurationProperties(prefix = "sentinel.flow-rules")
public class FlowRuleProps{
  //....省略
}



第二步：创建数据转换器，实现将 FlowRuleProps 转为 List<FlowRule>，如 FlowRuleConverter 所示。

public class FlowRuleConverter implements Converter<FlowRuleProps, List<FlowRule>>{

    @Override
    public List<FlowRule> convert(FlowRuleProps source){
       //....省略
    }
}



第三步：创建 FlowRuleDataSource，继承 AbstractDataSource，实现 readSource 方法。readSource 方法只需要获取 FlowRuleProps 返回即可，代码如下。

@Component
public class FlowRuleDataSource extends AbstractDataSource<FlowRuleProps, List<FlowRule>>{
    @Autowired
    private FlowRuleProps flowRuleProps;

    public FlowRuleDataSource() {
        super(new FlowRuleConverter());
    }
    @Override
    public FlowRuleProps readSource() throws Exception {
        return this.flowRuleProps;
    }
    @Override
    public void close() throws Exception {
    }
}



第四步：增强 FlowRuleDataSource，让 FlowRuleDataSource 能够监听到 FlowRuleProps 配置改变。

@Component
public class FlowRuleDataSource extends AbstractDataSource<FlowRuleProps, List<FlowRule>>
   implements ApplicationListener<RefreshScopeRefreshedEvent>,
        InitializingBean{
       // .....
    @Override
    public void onApplicationEvent(RefreshScopeRefreshedEvent event) {
        getProperty().updateValue(loadConfig());
    }

    @Override
    public void afterPropertiesSet() throws Exception {
        onApplicationEvent(new RefreshScopeRefreshedEvent());
    }
}




实现 InitializingBean 方法，在数据源对象创建时，初始化加载一次规则配置。
实现 ApplicationListener 接口，监听动态配置改变事件（RefreshScopeRefreshedEvent），在监听到事件时，首先调用 loadConfig 方法加载所有限流规则配置，然后调用 getProperty 方法获取 SentinelProperty，最后调用 SentinelProperty#updateValue 方法通知 FlowRuleManager 的监听器更新限流规则缓存。


第五步：写一个 ApplicationRunner 类，在 Spring 容器刷新完成后， 将数据源（FlowRuleDataSource）的 SentinelProperty 注册给 FlowRuleManager，代码如下。

@Component
public class FlowRuleDataSourceConfiguration implements ApplicationRunner{
    @Autowired
    private FlowRuleDataSource flowRuleDataSource;

    @Override
    public void run(ApplicationArguments args) throws Exception {
        // 将数据源的 SentinelProperty 注册给 FlowRuleManager
        FlowRuleManager.register2Property(flowRuleDataSource.getProperty());
    }
}



在调用 FlowRuleManager#register2Property 方法将 FlowRuleDataSource 动态数据源的 SentinelProperty 注册给 FlowRuleManager 时，FlowRuleManager 会自动给该 SentinelProperty 注册一个监听器（FlowPropertyListener）。

到此，一个基于 Spring Cloud 动态配置的限流规则动态数据源就已经完成，整个调用链路如下：


当动态配置改变时，Spring Cloud 会发出 RefreshScopeRefreshedEvent 事件，FlowRuleDataSource 的 onApplicationEvent 方法被调用；
FlowRuleDataSource 调用 loadConfig 方法获取最新的配置；
FlowRuleDataSource#loadConfig 调用 readSource 方法获取 FlowRuleProps 对象，此时的 FlowRuleProps 对象已经装载了最新的配置；
FlowRuleDataSource#loadConfig 调用转换器（FlowRuleConverter）的 convert 方法将 FlowRuleProps 对象转为 List<FlowRule> 对象；
FlowRuleDataSource 调用自身的 SentinelProperty 的 updateValue 方法通知所有监听器，并携带新的规则配置；
FlowPropertyListener 监听器的 configUpdate 方法被调用；
FlowPropertyListener 在 configUpdate 方法中更新 FlowRuleManager 缓存的限流规则。


总结

了解 Sentinel 实现动态数据源的原理后，我们可以灵活地自定义规则动态数据源，例如本篇介绍的，利用 Kubernetes 的 ConfigMap 资源存储规则配置，并通过 Spring Cloud 动态配置实现 Sentinel 的规则动态数据源。不仅如此，Sentinel 实现动态数据源的整体框架的设计也是值得我们学习的，如数据转换器、监听器。

                        
                        
                            