
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            18  技术趋势：如何为 Spring Security 添加响应式编程特性？
                            对于大多数日常业务场景而言，软件系统在任何时候都需要确保具备即时响应性。而响应式编程（Reactive Programming）就是用来构建具有即时响应性的是一种新的编程技术。随着 Spring 5 的发布，我们迎来了响应式编程的全新发展时期。而 Spring Security 作为 Spring 家族的一员，同样实现了一系列的响应式组件。今天我们就将围绕这些组件展开讨论。

什么是响应式编程？

在引入响应式 Spring Security 之前，我们先来介绍响应式编程中的一些基本概念，并给出 Spring 5 中所集成的响应式编程组件。

响应式编程的基本概念

在响应式系统中，任何操作都可以被看作是一种事件，而这些事件构成了数据流。这个数据流对于技术栈而言是一个全流程的概念。也就是说，无论是从底层数据库，向上到达服务层，最后到 Web 层，亦或是在这个流程中所包含的任意中间层组件，整个数据传递链路都应该是采用事件驱动的方式来进行运作。这样，我们就可以不采用传统的同步调用方式来处理数据，而是由位于数据库上游的各层组件自动来执行事件。这就是响应式编程的核心特点。

针对数据流的具体操作方法都定义在响应式流（Reactive Stream）规范中。在 Java 的世界中，关于响应式流规范的实现也有一些主流的开源框架，包括 RxJava、Vert.x 以及 Project Reactor。

Project Reactor

Spring 5 选择 Project Reactor 作为它的内置响应式编程框架，该框架提供了两种数据流的表示方式，即代表包含 0 到 n 个元素异步序列的 Flux 组件，以及只包含 0 个或 1 个元素的 Mono 组件。我们可以通过一个简单的代码示例来创建一个 Flux 对象，如下所示：

private Flux<Order> getAccounts() {

        List<Account> accountList = new ArrayList<>();

 

        Account account = new Account();

        account.setId(1L);

        account.setAccountCode("DemoCode");

        account.setAccountName("DemoName");

        accountList.add(account);

                

        return Flux.fromIterable(accountList);

}


在以上代码中，我们通过 Flux.fromIterable() 方法构建了 Flux 对象并进行返回，Flux.fromIterable() 是构建 Flux 的一种常用方法。而 Mono 组件也提供了一组有用的方法来创建 Mono 数据流，例如：

private Mono<Account> getAccountById(Long id) {   

        Account account = new Account();

        account.setId(id);

        account.setAccountCode("DemoCode");

        account.setAccountName("DemoName");

        accountList.add(account);

           

        return Mono.just(account);

}


可以看到，这里首先构建一个 Account 对象，然后通过 Mono.just() 方法返回一个 Mono 对象。

Spring WebFlux

同时，针对一个完整的应用程序开发过程，Spring 5 还专门提供了针对 Web 层的 WebFlux 框架、针对数据访问层的 Spring Data Reactive 框架等。因为 Spring Security 主要用于 Web 应用程序，所以这里对 WebFlux 做一些展开。

想要在 Spring Boot 中使用 WebFlux，需要引入如下依赖：

<dependency>

    <groupId>org.springframework.boot</groupId>

    <artifactId>spring-boot-starter-webflux</artifactId>

</dependency>  


请注意这里的 spring-boot-starter-webflux 就是构成响应式 Web 应用程序开发的基础。基于 WebFlux 构建响应式 Web 服务的编程模型，开发人员有两种选择。第一种是使用基于 Java 注解的方式，而第二种则是使用函数式编程模型。其中，基于 Java 注解的方式与使用 Spring MVC 完全一致，我们来看一个示例：

@RestController

public class HelloController {

 

    @GetMapping("/")

    public Mono<String> hello() {

        return Mono.just("Hello!");

    }

}


以上代码只有一个地方值得注意，即 hello() 方法的返回值从普通的 String 对象转化为了一个 Mono 对象。这点是完全可以预见的，使用 Spring WebFlux 与 Spring MVC 的不同之处，在于前者使用的类型都是 Reactor 中提供的 Flux 和 Mono 对象，而不是普通的 POJO。

我们知道传统的 Spring MVC 构建在 Java EE 的 Servlet 标准之上，该标准本身就是阻塞式和同步的。而 Spring WebFlux 则是构建在响应式流以及它的实现框架 Project Reactor 之上的一个开发框架，因此可以基于 HTTP 协议来构建异步非阻塞的 Web 服务。

最后，我们来看一下位于底部的容器支持。当你使用 Spring WebFlux 时，你会注意到它默认采用了 Netty 作为运行时容器。这是因为 Spring MVC 是运行在传统的 Servlet 容器之上，而 Spring WebFlux 则需要支持异步的运行环境，比如 Netty、Undertow 以及 Servlet 3.1 之上的 Tomcat 和 Jetty。这里多说一句，因为在 Servlet 3.1 中引入了异步 I/O 支持。

引入响应式 Spring Security

对于 Spring Security 而言，引入响应式编程技术同样会对传统实现方法带来一些变化。比方说，在[《认证体系：如何深入理解 Spring Security 的认证机制？]课时中，我们已经知道 UserDetailsService 的作用是用来获取用户信息，你可以把它理解为是一种数据源，这样针对数据源的数据访问过程同样需要支持响应式编程。下面让我们一起来看一下这些变化吧。

响应式用户认证

在响应式 Spring Security 中，提供了一个响应式版本的 UserDetailsService，即 ReactiveUserDetailsService，定义如下：

public interface ReactiveUserDetailsService {

 

    Mono<UserDetails> findByUsername(String username);

}


请注意，这里的 findByUsername() 方法返回的是一个 Mono 对象。ReactiveUserDetailsService 接口有一个实现类 MapReactiveUserDetailsService，提供了基于内存的用户信息存储方案，实现过程如下所示：

public class MapReactiveUserDetailsService implements ReactiveUserDetailsService, ReactiveUserDetailsPasswordService {

    private final Map<String, UserDetails> users;

 

    public MapReactiveUserDetailsService(Map<String, UserDetails> users) {

        this.users = users;

    }

 

    public MapReactiveUserDetailsService(UserDetails... users) {

        this(Arrays.asList(users));

    }

 

    public MapReactiveUserDetailsService(Collection<UserDetails> users) {

        Assert.notEmpty(users, "users cannot be null or empty");

        this.users = new ConcurrentHashMap<>();

        for (UserDetails user : users) {

             this.users.put(getKey(user.getUsername()), user);

        }

    }

 

    @Override

    public Mono<UserDetails> findByUsername(String username) {

        String key = getKey(username);

        UserDetails result = users.get(key);

        return result == null ? Mono.empty() : Mono.just(User.withUserDetails(result).build());

    }

 

    @Override

    public Mono<UserDetails> updatePassword(UserDetails user, String newPassword) {

        return Mono.just(user)

                 .map(u ->

                     User.withUserDetails(u)

                         .password(newPassword)

                         .build()

                 )

                 .doOnNext(u -> {

                     String key = getKey(user.getUsername());

                     this.users.put(key, u);

                 });

    }

 

    private String getKey(String username) {

        return username.toLowerCase();

    }

}


从上面的代码中可以看到，这里使用了一个 Map 来保存用户信息，然后在 findByUsername() 方法中，通过 Mono.just() 方法来返回一个 Mono 对象。然后，我们还注意到在 updatePassword() 方法中用到的 map() 方法，实际上是 Project Reactor 所提供的一个操作符，用于实现对一个对象执行映射操作。

基于 MapReactiveUserDetailsService，我们就可以在业务系统中通过以下方式构建一个 ReactiveUserDetailsService：

@Bean

public ReactiveUserDetailsService userDetailsService() {

        UserDetails u = User.withUsername("john")

                .password("12345")

                .authorities("read")

                .build();

 

        ReactiveUserDetailsService uds = new MapReactiveUserDetailsService(u);

 

        return uds;

}


当然，针对用户认证，响应式 Spring Security 也提供了响应式版本的 ReactiveAuthenticationManager 来执行具体的认证流程。

响应式授权机制

介绍完认证，我们接着来看授权，假设系统中存在这样一个简单的 HTTP 端点：

@RestController

public class HelloController {

 

    @GetMapping("/hello")

    public Mono<String> hello(Mono<Authentication> auth) {

        Mono<String> message = auth.map(a -> "Hello " + a.getName());

        return message;

    }

}


这里我们使用了 Spring Webflux 构建了一个响应式端点，注意到 hello() 的返回值是一个 Mono 对象。同时，我们输入的也是一个 Mono 对象，因此，访问这个端点显然是需要认证的。

在《访问授权：如何对请求的安全访问过程进行有效配置？》课时中，我们知道可以通过覆写 WebSecurityConfigurerAdapter 中的 configure(HttpSecurity http) 方法来设置访问权限。这种配置方法在响应式编程体系中就无法再使用了，取而代之的是使用一个叫 SecurityWebFilterChain 的配置接口来完成配置，该接口定义如下：

public interface SecurityWebFilterChain {

 

    //评估交互上下文 ServerWebExchange 是否匹配

    Mono<Boolean> matches(ServerWebExchange exchange);

    

    //一组过滤器

    Flux<WebFilter> getWebFilters();

}


从命名上看，我们不难理解 SecurityWebFilterChain 代表的实际上是一个过滤器链，而 ServerWebExchange 则是包含请求和响应的一种交互上下文，这在响应式环境中是一种固定属性，因为我们认为整个交互过程不是在单纯的发送请求和接受响应，而是在交换（Exchange）数据。如果想要使用 SecurityWebFilterChain，可以采用类似如下所示的代码示例：

@Bean

public SecurityWebFilterChain securityWebFilterChain(ServerHttpSecurity http) {

        return http.authorizeExchange()

                .pathMatchers(HttpMethod.GET, "/hello").authenticated()

                .anyExchange().permitAll()

                    .and()

                .httpBasic()

                    .and()

                .build();

}


这里的 ServerHttpSecurity 可以用来构建 SecurityWebFilterChain 的实例，它的作用类似于非响应式系统中所使用的 HttpSecurity。同时，ServerHttpSecurity 也提供了一组我们熟悉的配置方法来设置各种认证和授权机制。

需要注意的是，在响应式系统中，因为处理的对象是 ServerWebExchange，而不是传统的 ServerRequest，所以在涉及与请求相关的方法命名时都统一做了调整。例如使用了 authorizeExchange() 方法来取代 authorizeRequests()，使用 anyExchange() 取代了 anyRequest()，而这里的 pathMatchers() 方法也可以等同于以前介绍的 mvcMatchers() 方法。

响应式方法级别访问控制

在[《 10 |全局方法：如何确保方法级别的安全访问？》]课时中，我们介绍了 Spring Security 所提供的一个非常强大的功能，即全局安全方法机制。通过这种机制，无论是 Web 服务还是普通应用，都可以基于方法的执行过程来应用授权规则。

在响应式编程中，我们称这种方法级别的授权机制为响应式方法安全（Reactive Method Security）机制，以便与传统的全局方法安全机制进行区分。

想要在应用程序中使用响应式方法安全机制，我们还需要专门引入一个新的注解，即 @EnableReactiveMethodSecurity。这个注解与 @EnableGlobalMethodSecurity 注解类似，用来启用响应式安全方法机制：

@Configuration

@EnableGlobalMethodSecurity

public class SecurityConfig 


现在，我们来看一下使用响应式方法安全机制的代码示例：

@RestController

public class HelloController {

 

    @GetMapping("/hello")

    @PreAuthorize("hasRole('ADMIN')")

    public Mono<String> hello() {

        return Mono.just("Hello!");

    }

}


可以看到，这里使用了 @PreAuthorize 注解，并通过”hasRole(‘ADMIN’)“这一 SpEL 表达式来实现基于角色的授权机制。就这个注解的使用方式而言，可以发现，它与传统应用程序中的使用方式是一致的。但不幸的是，就目前而言，响应式方法安全机制还不是很成熟，只提供了 @PreAuthorize 和 @PostAuthorize 这对注解，而 @PreFilter 和 @PostFilter 注解还没有实现。

小结与预告

响应式编程是技术发展趋势，为我们构建高弹性的应用程序提供了一种新的编程模式。作为 Spring 家族中的重要组成部分，Spring Security 框架同样提供了对响应式编程的全面支持。本课时对响应式编程的基础概念做了阐述，并给出了 Spring Security 中关于用户认证、授权机制以方法级别访问控制等功能的响应式解决方案。

这里给你留一道思考题：在实现授权机制时，响应式编程模式与传统编程模式有什么区别？

                        
                        
                            