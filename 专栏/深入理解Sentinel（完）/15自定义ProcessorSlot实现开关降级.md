
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            15 自定义 ProcessorSlot 实现开关降级
                            开关降级在我们公司的电商项目中是每个微服务都必须支持的一项功能，主要用于活动期间、每日流量高峰期间、主播带货期间关闭一些无关紧要功能，降低数据库的压力。

开关降级实现起来很简单，例如，我们可以使用 Spring AOP 或者动态代理模式拦截目标方法的执行，在方法执行之前，先根据 key 从 Redis 读取 value，如果 value 是 true，则不执行目标方法，直接响应服务降级。这种方式付出的性能损耗就只有一次 redis 的 get 操作，如果不想每个请求都读 Redis 缓存，也可以通过动态配置方式，使用配置中心去管理开关。

使用 Spring AOP 实现开关降级功能

以 Redis 缓存开关为例，使用切面实现开关降级只需要三步：定义注解、实现开关降级切面、在需要使用开关降级的接口方法上添加开关降级注解。

\1. 定义开关降级注解 @SwitchDegrade，代码如下：

@Retention(RetentionPolicy.RUNTIME)
@Target({ElementType.METHOD})
public @interface SwitchDegrade {
    // 缓存 key
    String key() default "";
}



提示：如果是应用在实际项目中，建议为 @SwitchDegrade 注解添加一个前缀属性，限制同一个应用下的开关 key 都是有同一个前缀，避免多个应用之间的缓存 key 冲突。

\2. 实现切面 SwitchDegradeAspect，拦截接口方法的执行，代码如下：

@Aspect
public class SwitchDegradeAspect {
    // 切点定义
    @Pointcut("@annotation(com.wujiuye.demo.common.sentinel.SwitchDegrade)")
    public void degradePointCut() {
    }

    /**
     * 拦截请求判断是否开启开关降级
     */
    @Around("degradePointCut()&&@annotation(switchDegrade)")
    public Object around(ProceedingJoinPoint point, SwitchDegrade switchDegrade) throws Throwable {
        String cacheKey = switchDegrade.key();
        RedisTemplate redisTemplate = SpringContextUtils.getBean(RedisTemplate.class);
        String value = redisTemplate.get(cacheKey);
        if ("true".equalsIgnoreCase(value)) {
            throw new SwitchDegradeException(cacheKey, "开关降级打开");
        }
        return point.proceed();
    }
}



如代码所示，SwitchDegradeAspect 拦截目标方法的执行，先从方法上的 @SwitchDegrade 注解获取开关的缓存 key，根据 key 从 redis 读取当前开关的状态，如果 key 存在且 value 为 true，则抛出一个开关降级异常。

当开关打开时，SwitchDegradeAspect 并不直接响应请求，而是抛出异常由全局异常处理器处理，这是因为并不是每个接口方法都会有返回值，且返回值类型也不固定。所以还需要在全局异常处理器处理开关降级抛出的异常，代码如下：

    @ExceptionHandler(SwitchDegradeException.class)
    public BaseResponse handleSwitchDegradeException(SwitchDegradeException ex) {
        log.error("Switch Degrade! switch key is:{}, message:{}", ex.getSwitchKey(), ex.getMessage());
        return new BaseResponse(ResultCode.SERVICE_DEGRADE, ex.getMessage());
    }



提示：如果是整合 OpenFeign 使用，且配置了 Fallback，则全局异常可以不配置。

\3. 在需要被开关控制的接口方法上使用 @SwitchDegrade 注解，例如：

@RestController
@RequestMapping("/v1/test")
public class DemoController {
    @SwitchDegrade(key = "auth:switch")
    @PostMapping("/demo")
    public GenericResponse<Void> demo(@RequestBody Invocation<DemoFrom> invocation) {
       //.....
    }
}



这种方式虽然能满足需求，但也有一个缺点，就是必须要在方法上添加 @SwitchDegrade 注解，配置不够灵活，但也不失为一个好方法。

基于 Sentinel 自定义 ProcessorSlot 实现开关降级功能

Sentinel 将降级功能的实现抽象为处理器插槽（ProcessorSlot），由一个个 ProcessorSlot 提供丰富的降级功能的实现，并且使用 SPI 机制提供扩展功能，使用者可通过自定义 SlotChainBuilder 自己构建 ProcessorSlotChain，这相当于给我们提供插件的功能。因此，我们可以通过自定义 ProcessorSlot 为 Sentinel 添加开关降级功能。

与熔断降级、限流降级一样，我们也先定义开关降级规则类，实现 loadRules API；然后提供一个 Checker，由 Checker 判断开关是否打开，是否需要拒绝当前请求；最后自定义 ProcessorSlot 与 SlotChainBuilder。

与使用切面实现开关降级有所不同，使用 Sentinel 实现开关降级我们不需要再在接口方法或者类上添加注解，我们想要实现的是与熔断降级、限流降级一样全部通过配置规则实现，这也是我们为什么选择基于 Sentinel 实现开关降级功能的原因。

通常，一个开关会控制很多的接口，而不仅仅只是一个，所以，一个开关对应一个降级规则，一个降级规则可配置多个资源。开关降级规则类 SwitchRule 实现代码如下：

@Data
@ToString
public class SwitchRule {
    public static final String SWITCH_KEY_OPEN = "open";
    public static final String SWITCH_KEY_CLOSE = "close";
    // 开关状态
    private String status = SWITCH_KEY_OPEN;
    // 开关控制的资源
    private Resources resources;
    @Data
    @ToString
    public static class Resources {
        // 包含
        private Set<String> include;
        // 排除
        private Set<String> exclude;
    }
}



灵活，不仅仅只是去掉注解的使用，更需要可以灵活指定开关控制某些资源，因此，配置开关控制的资源应支持这几种情况：指定该开关只控制哪些资源、除了某些资源外其它都受控制、控制全部。所以，SwitchRule 的资源配置与 Sentinel 的熔断降级、限流降级规则配置不一样，SwitchRule 支持三种资源配置方式：


如果资源不配置，则开关作用到全部资源；
如果配置 inclode，则作用到 include 指定的所有资源；
如果不配置 inclode 且配置了 exclude，则除了 exclude 指定的资源外，其它资源都受这个开关的控制。


接着实现 loadRules API。在 Sentinel 中，提供 loadRules API 的类通常命名为 XxxRuleManager，即 Xxx 规则管理器，所以我们定义的开关降级规则管理器命名为 SwitchRuleManager。SwitchRuleManager 的实现代码如下：

public class SwitchRuleManager {
    private static volatile Set<SwitchRule> switchRuleSet = new HashSet<>();
    public static void loadSwitchRules(Set<SwitchRule> rules) {
        SwitchRuleManager.switchRuleSet = rules;
    }
    static Set<SwitchRule> getRules() {
        return switchRuleSet;
    }
}



SwitchRuleManager 提供两个接口：


loadSwitchRules：用于更新或者加载开关降级规则
getRules：获取当前生效的全部开关降级规则


同样地，在 Sentinel 中，一般会将检查规则是否达到触发降级的阈值由 XxxRuleChecker 完成，即 Xxx 规则检查员，所以我们定义的开关降级规则检查员命名为 SwitchRuleChecker，由 SwitchRuleChecker 检查开关是否打开，如果开关打开则触发开关降级。SwitchRuleChecker 的代码实现如下：

public class SwitchRuleChecker {

    public static void checkSwitch(ResourceWrapper resource, Context context) throws SwitchException {
        Set<SwitchRule> switchRuleSet = SwitchRuleManager.getRules();
        // 遍历规则
        for (SwitchRule rule : switchRuleSet) {
            // 判断开关状态，开关未打开则跳过
            if (!rule.getStatus().equalsIgnoreCase(SwitchRule.SWITCH_KEY_OPEN)) {
                continue;
            }
            if (rule.getResources() == null) {
                continue;
            }
            // 实现 include 语意
            if (!CollectionUtils.isEmpty(rule.getResources().getInclude())) {
                if (rule.getResources().getInclude().contains(resource.getName())) {
                    throw new SwitchException(resource.getName(), "switch");
                }
            }
            // 实现 exclude 语意
            if (!CollectionUtils.isEmpty(rule.getResources().getExclude())) {
                if (!rule.getResources().getExclude().contains(resource.getName())) {
                    throw new SwitchException(resource.getName(), "switch");
                }
            }
        }
    }

}



如代码所示，SwitchRuleChecker 从 SwitchRuleManager 获取配置的开关降级规则，遍历开关降级规则，如果开关打开，且匹配到当前资源名称被该开关控制，则抛出 SwitchException。

SwitchException 需继承 BlockException，抛出的 SwitchException 如果不被捕获，则由全局异常处理器处理。一定是要抛出 BlockException 的子类，否则抛出的异常会被资源指标数据统计收集，会影响到熔断降级等功能的准确性。

虽然 SwitchRuleChecker 使用了 for 循环遍历开关降级规则，但一个项目中的开关是很少的，一般就一个或者几个。

与熔断降级、限流降级一样，开关降级也支持一个资源被多个开关规则控制。

最后，还需要自定义实现开关降级功能的切入点 SwitchSlot。SwitchSlot 继承 AbstractLinkedProcessorSlot，在 entry 方法中调用 SwitchRuleChecker#checkSwitch 方法检查当前资源是否已经降级。SwitchSlot 的代码实现如下：

public class SwitchSlot extends AbstractLinkedProcessorSlot<Object> {

    @Override
    public void entry(Context context, ResourceWrapper resourceWrapper, Object param, int count, boolean prioritized, Object... args) throws Throwable {
        SwitchRuleChecker.checkSwitch(resourceWrapper, context);
        fireEntry(context, resourceWrapper, param, count, prioritized, args);
    }

    @Override
    public void exit(Context context, ResourceWrapper resourceWrapper, int count, Object... args) {
        fireExit(context, resourceWrapper, count, args);
    }

}



自定义 ProcessorSlotChain 构建器 MySlotChainBuilder，将自定义的 SwitchSlot 添加到 ProcessorSlot 链表的末尾。当然，可以添加到任何位置，因为 SwitchSlot 没有用到指标数据，SwitchSlot 放置何处都不会影响到 Sentinel 的正常工作。

MySlotChainBuilder 代码实现如下：

public class MySlotChainBuilder extends DefaultSlotChainBuilder {
    @Override
    public ProcessorSlotChain build() {
        ProcessorSlotChain chain = super.build();
        chain.addLast(new SwitchSlot());
        return chain;
    }
}



MySlotChainBuilder 继承 DefaultSlotChainBuilder 只是为了使用 DefaultSlotChainBuilder#build 方法，简化 ProcessorSlotChain 的构造步骤，只需要在 DefaultSlotChainBuilder 构造的链表尾部添加一个 SwitchSlot 即可。

现在 MySlotChainBuilder 生效了吗？当然还不行，还需要将 MySlotChainBuilder 注册到 SlotChainBuilder 接口的配置文件。

在当前项目的 resources 资源目录的 META-INF/service 目录下创建一个名为“com.alibaba.csp.sentinel.slotchain.SlotChainBuilder”的文件，在该文件中配置 MySlotChainBuilder 类的全名，例如：

com.wujiuye.demo.common.sentinel.MySlotChainBuilder



现在，您可以在 MySlotChainBuilder#build 方法中添加断点，然后启动项目，正常情况下程序会在该断点停下。但由于我们并未配置开关降级规则，所以还看不到效果。

我们可以写一个配置类，在配置类调用 SwitchRuleManager#loadRules API 添加开关降级规则。例如：

@Configuration
public class SentinelRuleConfiguration{

    static {
           Set<SwitchRule> rules = new HashSet<>();
            // 
            SwitchRule rule = new SwitchRule();
            rule.setStatus(SwitchRule.SWITCH_KEY_OPEN);
            SwitchRule.Resources resources = new SwitchRule.Resources();
            Set<String> include = new HashSet<>();
            include.add("/v1/test/demo");
            resources.setInclude(include);
            // 
            rules.add(rule);
            SwitchRuleManager.loadSwitchRules(rules);
    }
}



上面代码配置了一个开关降级规则，该降级规则只控制接口（资源）"/v1/test/demo"，SwitchRule.status 控制开关是否打开，测试这里就不演示了。当然，这种配置方式只适用于本地测试，实际项目中我们可通过动态配置实现，这将在后面介绍 Sentinel 动态数据源时再介绍如何实现。

提示：实现动态配置规则并不一定需要使用 Sentinel 的动态数据源。

                        
                        
                            