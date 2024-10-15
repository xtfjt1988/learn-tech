
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            33  动手实现一个简单的RPC框架（三）：客户端
                            你好，我是李玥。

上节课我们已经一起实现了这个 RPC 框架中的两个基础组件：序列化和网络传输部分，这节课我们继续来实现这个 RPC 框架的客户端部分。

在《[31 | 动手实现一个简单的 RPC 框架（一）：原理和程序的结构]》这节课中我们提到过，在 RPC 框架中，最关键的就是理解“桩”的实现原理，桩是 RPC 框架在客户端的服务代理，它和远程服务具有相同的方法签名，或者说是实现了相同的接口，客户端在调用 RPC 框架提供的服务时，实际调用的就是“桩”提供的方法，在桩的实现方法中，它会发请求到服务端获取调用结果并返回给调用方。

在 RPC 框架的客户端中，最关键的部分，也就是如何来生成和实现这个桩。

如何来动态地生成桩？

RPC 框架中的这种桩的设计，它其实采用了一种设计模式：“代理模式”。代理模式给某一个对象提供一个代理对象，并由代理对象控制对原对象的引用，被代理的那个对象称为委托对象。

在 RPC 框架中，代理对象都是由 RPC 框架的客户端来提供的，也就是我们一直说的“桩”，委托对象就是在服务端，真正实现业务逻辑的服务类的实例。



我们最常用 Spring 框架，它的核心 IOC（依赖注入）和 AOP（面向切面）机制，就是这种代理模式的一个实现。我们在日常开发的过程中，可以利用这种代理模式，在调用流程中动态地注入一些非侵入式业务逻辑。

这里的“非侵入”指的是，在现有的调用链中，增加一些业务逻辑，而不用去修改调用链上下游的代码。比如说，我们要监控一个方法 A 的请求耗时，普通的方式就是在方法的开始和返回这两个地方各加一条记录时间的语句，这种方法就需要修改这个方法的代码，这是一种“侵入式”的方式。

我们还可以给这个方法所在的类创建一个代理类，在这个代理类的 A 方法中，先记录开始时间，然后调用委托类的 A 方法，再记录结束时间。把这个代理类加入到调用链中，就可以实现“非侵入式”记录耗时了。同样的方式，我们还可以用在权限验证、风险控制、调用链跟踪等等这些场景中。

下面我们来看下，在我们这个 RPC 框架的客户端中，怎么来实现的这个代理类，也就是“桩”。首先我们先定一个 StubFactory 接口，这个接口就只有一个方法：

public interface StubFactory {
    <T> T createStub(Transport transport, Class<T> serviceClass);
}


这个桩工厂接口只定义了一个方法 createStub，它的功能就是创建一个桩的实例，这个桩实现的接口可以是任意类型的，也就是上面代码中的泛型 T。这个方法有两个参数，第一个参数是一个 Transport 对象，这个 Transport 我们在上节课介绍过，它是用来给服务端发请求的时候使用的。第二个参数是一个 Class 对象，它用来告诉桩工厂：我需要你给我创建的这个桩，应该是什么类型的。createStub 的返回值就是由工厂创建出来的桩。

如何来实现这个工厂方法，创建桩呢？这个桩它是一个由 RPC 框架生成的类，这个类它要实现给定的接口，里面的逻辑就是把方法名和参数封装成请求，发送给服务端，然后再把服务端返回的调用结果返回给调用方。这里我们已经解决了网络传输和序列化的问题，剩下一个核心问题就是如何来生成这个类了。

我们知道，普通的类它是由我们编写的源代码，通过编译器编译之后生成的。那 RPC 框架怎么才能根据要实现的接口来生成一个类呢？在这一块儿，不同的 RPC 框架的实现是不一样的，比如，gRPC 它是在编译 IDL 的时候就把桩生成好了，这个时候编译出来桩，它是目标语言的源代码文件。比如说，目标语言是 Java，编译完成后它们会生成一些 Java 的源代码文件，其中以 Grpc.java 结尾的文件就是生成的桩的源代码。这些生成的源代码文件再经过 Java 编译器编译以后，就成了桩。

而 Dubbo 是在运行时动态生成的桩，这个实现就更加复杂了，并且它利用了很多 Java 语言底层的特性。但是它的原理并不复杂，Java 源代码编译完成之后，生成的是一些 class 文件，JVM 在运行的时候，读取这些 Class 文件来创建对应类的实例。

这个 Class 文件虽然非常复杂，但本质上，它里面记录的内容，就是我们编写的源代码中的内容，包括类的定义，方法定义和业务逻辑等等，并且它也是有固定的格式的。如果说，我们按照这个格式，来生成一个 class 文件，只要这个文件的格式是符合 Java 规范的，JVM 就可以识别并加载它。这样就不需要经过源代码、编译这些过程，直接动态来创建一个桩。

由于动态生成 class 文件这部分逻辑和 Java 语言的特性是紧密关联的，考虑有些同学并不熟悉 Java 语言，所以在这个 RPC 的例子中，我们采用一种更通用的方式来动态生成桩。我们采用的方式是：先生成桩的源代码，然后动态地编译这个生成的源代码，然后再加载到 JVM 中。

为了让这部分代码不会过于复杂，便于你快速理解，我们限定：服务接口只能有一个方法，并且这个方法只能有一个参数，参数和返回值的类型都是 String 类型。你在学会这部分动态生成桩的原理之后，很容易重构这部分代码来解除这个限定，无非是多遍历几次方法和参数而已。

我之前讲过，我们需要动态生成的这个桩，它每个方法的逻辑都是一样的，都是把类名、方法名和方法的参数封装成请求，然后发给服务端，收到服务端响应之后再把结果作为返回值，返回给调用方。所以，我们定义一个 AbstractStub 的抽象类，在这个类中实现大部分通用的逻辑，让所有动态生成的桩都继承这个抽象类，这样动态生成桩的代码会更少一些。

下面我们来实现客户端最关键的这部分代码：实现这个 StubFactory 接口动态生成桩。

public class DynamicStubFactory implements StubFactory{
    private final static String STUB_SOURCE_TEMPLATE =
            "package com.github.liyue2008.rpc.client.stubs;\n" +
            "import com.github.liyue2008.rpc.serialize.SerializeSupport;\n" +
            "\n" +
            "public class %s extends AbstractStub implements %s {\n" +
            "    @Override\n" +
            "    public String %s(String arg) {\n" +
            "        return SerializeSupport.parse(\n" +
            "                invokeRemote(\n" +
            "                        new RpcRequest(\n" +
            "                                \"%s\",\n" +
            "                                \"%s\",\n" +
            "                                SerializeSupport.serialize(arg)\n" +
            "                        )\n" +
            "                )\n" +
            "        );\n" +
            "    }\n" +
            "}";
 
    @Override
    @SuppressWarnings("unchecked")
    public <T> T createStub(Transport transport, Class<T> serviceClass) {
        try {
            // 填充模板
            String stubSimpleName = serviceClass.getSimpleName() + "Stub";
            String classFullName = serviceClass.getName();
            String stubFullName = "com.github.liyue2008.rpc.client.stubs." + stubSimpleName;
            String methodName = serviceClass.getMethods()[0].getName();
 
            String source = String.format(STUB_SOURCE_TEMPLATE, stubSimpleName, classFullName, methodName, classFullName, methodName);
            // 编译源代码
            JavaStringCompiler compiler = new JavaStringCompiler();
            Map<String, byte[]> results = compiler.compile(stubSimpleName + ".java", source);
            // 加载编译好的类
            Class<?> clazz = compiler.loadClass(stubFullName, results);
            // 把 Transport 赋值给桩
            ServiceStub stubInstance = (ServiceStub) clazz.newInstance();
            stubInstance.setTransport(transport);
            // 返回这个桩
            return (T) stubInstance;
        } catch (Throwable t) {
            throw new RuntimeException(t);
        }
    }
}


一起来看一下这段代码，静态变量 STUB_SOURCE_TEMPLATE 是桩的源代码的模板，我们需要做的就是，填充模板中变量，生成桩的源码，然后动态的编译、加载这个桩就可以了。

先来看这个模板，它唯一的这个方法中，就只有一行代码，把接口的类名、方法名和序列化后的参数封装成一个 RpcRequest 对象，调用父类 AbstractStub 中的 invokeRemote 方法，发送给服务端。invokeRemote 方法的返回值就是序列化的调用结果，我们在模板中把这个结果反序列化之后，直接作为返回值返回给调用方就可以了。

再来看下面的 createStrub 方法，从 serviceClass 这个参数中，可以取到服务接口定义的所有信息，包括接口名、它有哪些方法、每个方法的参数和返回值类型等等。通过这些信息，我们就可以来填充模板，生成桩的源代码。

桩的类名就定义为：“接口名 + Stub”，为了避免类名冲突，我们把这些桩都统一放到固定的包 com.github.liyue2008.rpc.client.stubs 下面。填充好模板生成的源代码存放在 source 变量中，然后经过动态编译、动态加载之后，我们就可以拿到这个桩的类 clazz，利用反射创建一个桩的实例 stubInstance。把用于网络传输的对象 transport 赋值给桩，这样桩才能与服务端进行通信。到这里，我们就实现了动态创建一个桩。

使用依赖倒置原则解耦调用者和实现

在这个 RPC 框架的例子中，很多地方我们都采用了同样一种解耦的方法：通过定义一个接口来解耦调用方和实现。在设计上这种方法称为“依赖倒置原则（Dependence Inversion Principle）”，它的核心思想是，调用方不应依赖于具体实现，而是为实现定义一个接口，让调用方和实现都依赖于这个接口。这种方法也称为“面向接口编程”。它的好处我们之前已经反复说过了，可以解耦调用方和具体的实现，不仅实现是可替换的，实现连同定义实现的接口也是可以复用的。

比如，我们上面定义的 StubFactory 它是一个接口，它的实现类是 DynamicStubFactory，调用方是 NettyRpcAccessPoint，调用方 NettyAccessPoint 并不依赖实现类 DynamicStubFactory，就可以调用 DynamicStubFactory 的 createStub 方法。

要解耦调用方和实现类，还需要解决一个问题：谁来创建实现类的实例？一般来说，都是谁使用谁创建，但这里面我们为了解耦调用方和实现类，调用方就不能来直接创建实现类，因为这样就无法解耦了。那能不能用一个第三方来创建这个实现类呢？也是不行的，即使用一个第三方类来创建实现，那依赖关系就变成了：调用方依赖第三方类，第三方类依赖实现类，调用方还是间接依赖实现类，还是没有解耦。

这个问题怎么来解决？没错，使用 Spring 的依赖注入是可以解决的。这里再给你介绍一种 Java 语言内置的，更轻量级的解决方案：SPI（Service Provider Interface）。在 SPI 中，每个接口在目录 META-INF/services/ 下都有一个配置文件，文件名就是以这个接口的类名，文件的内容就是它的实现类的类名。还是以 StubFactory 接口为例，我们看一下它的配置文件：

$cat rpc-netty/src/main/resources/META-INF/services/com.github.liyue2008.rpc.client.StubFactory
com.github.liyue2008.rpc.client.DynamicStubFactory


只要把这个配置文件、接口和实现类都放到 CLASSPATH 中，就可以通过 SPI 的方式来进行加载了。加载的参数就是这个接口的 class 对象，返回值就是这个接口的所有实现类的实例，这样就在“不依赖实现类”的前提下，获得了一个实现类的实例。具体的实现代码在 ServiceSupport 这个类中。

小结

这节课我们一起实现了这个 RPC 框架的客户端，在客户端中，最核心的部分就是桩，也就是远程服务的代理类。在桩中，每个方法的逻辑都是一样的，就是把接口名、方法名和请求的参数封装成一个请求发给服务端，由服务端调用真正的业务类获取结果并返回给客户端的桩，桩再把结果返回给调用方。

客户端实现的难点就是，如何来动态地生成桩。像 gRPC 这类多语言的 RPC 框架，都是在编译 IDL 的过程中生成桩的源代码，再和业务代码，使用目标语言的编译器一起编译的。而像 Dubbo 这类没有编译过程的 RPC 框架，都是在运行时，利用一些语言动态特性，动态创建的桩。

RPC 框架的这种“桩”的设计，其实是一种动态代理设计模式。这种设计模式可以在不修改源码，甚至不需要源码的情况下，在调用链中注入一些业务逻辑。这是一种非常有用的高级技巧，可以用在权限验证、风险控制、调用链跟踪等等很多场景中，希望你能掌握它的实现原理。

最后我们介绍的依赖倒置原则，可以非常有效地降低系统各部分之间的耦合度，并且不会过度增加系统的复杂度，建议你在设计软件的时候广泛的采用。其实你想一下，现在这么流行的微服务思想，其实就是依赖倒置原则的实践。只是在微服务中，它更极端地把调用方和实现分离成了不同的软件项目，实现了完全的解耦。

                        
                        
                            