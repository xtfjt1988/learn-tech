
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            16  基于 DDD 的代码设计演示（含 DDD 的技术中台设计）
                            我这些年的从业经历，起初是作为项目经理带团队做软件研发，后来转型成为架构师，站在更高的层面去思考软件研发的那些事儿。我认为，一个成熟的软件研发团队：


不仅在于团队成员研发水平的提高；
更在于将不断积累的通用的设计方法与技术框架，沉淀到底层的技术中台中。


只要有了这样的技术中台作为支撑，才能让研发团队具备更强的能力，用更快的速度，研发出更多的产业，以快速适应激烈竞争而快速变化的市场。

譬如，团队某次接到了一个数据推送的需求，在完成了该需求并交付用户以后，就在这个功能设计的基础上，抽取共性、保留个性，将其下沉到技术中台形成“数据共享平台”的设计。有了这个功能，团队日后在接到类似需求时，只需要进行一些配置或者简单开发，就能交付用户啦。

这样，团队的研发能力就大大提升了。团队研发的功能越多，沉淀到技术中台的功能就越多，团队研发能力的提升就越大。只有这样的技术中台才能支撑研发团队的快速交付，关键是要有人、有意识地去做这些工作的整理，而我们团队是在“使能故事”中完成这些工作的。

现如今，越来越多的团队采用敏捷开发，在 2~3 周的迭代周期中规划并完成“用户故事”。“用户故事”是需要紧急应对的用户需求，但如果不能提升团队的能力，那么团队就会像救火队员一样永远是在应对用户需求的“火”而疲于奔命。

相反，“使能故事（Enabler Story）”就是为了提升我们的能力，从而更快速地应对用户需求。俗话说：“磨刀不误砍柴工”，“使能故事”就是“磨刀”，它虽然要耗费一些时间，但可以让日后的“砍柴”更快更好，是很值得的。

因此，一个成熟的团队在每次的迭代中不能只是完成“用户故事”，而应该拿出一定比例的时间完成“使能故事”，使团队日后的“用户故事”做得更快，实现快速交付。

我的支持 DDD + 微服务的技术中台就是在这种指导下逐渐形成的。之前在我的团队实践 DDD + 微服务的过程中，遇到了很多的阻力。这种阻力要求团队成员花更多的时间学习 DDD 相关知识，用正确的方法与步骤去设计开发，并做到位。然而，当他们真正做到位以后，却发现 DDD 的设计开发非常烦琐，要频繁地实现各种工厂、仓库、数据补填等开发工作，使开发人员对 DDD 的开发心生厌恶。以往项目经理在面对这些问题时，只能从管理上制定开发规范，但这样的措施于事无补。

而我站在架构师的角度，去设计技术框架，在原有代码的基础上，抽取共性、保留个性，将烦琐的 DDD 开发封装在了技术中台中。这样做，不仅简化了设计开发，使得 DDD 更容易在项目中落地，还规范了代码，使得业务开发人员没有机会去编写 Controller 与 Dao 代码，自然而然地将业务代码基于领域模型设计在了 Service 与领域对象中了。接着，来看看这个框架的设计。

整个演示代码的架构

我把整个演示代码分享在了 GitHub 中，它分为这样几个项目。


demo-ddd-trade：一个基于 DDD 设计的单体应用。
demo-parent：本示例所有微服务项目的父项目。
demo-service-eureka：微服务注册中心 eureka。
demo-service-config：微服务配置中心 config。
demo-service-turbine：各微服务断路器监控 turbine。
demo-service-zuul：服务网关 zuul。
demo-service-parent：各业务微服务（无数据库访问）的父项目。
demo-service-support：各业务微服务（无数据库访问）底层技术框架。
demo-service-customer：用户管理微服务（无数据库访问）。
demo-service-product：产品管理微服务（无数据库访问）。
demo-service-supplier：供应商管理微服务（无数据库访问）。
demo-service2-parent：各业务微服务（有数据库访问）的父项目。
demo-service2-support：各业务微服务（有数据库访问）底层技术框架。
demo-service2-customer：用户管理微服务（有数据库访问）。
demo-service2-product：产品管理微服务（有数据库访问）。
demo-service2-supplier：供应商管理微服务（有数据库访问）。
demo-service2-order：订单管理微服务（有数据库访问）。


总之，这里有一个基于 DDD 的单体应用与一个完整的微服务应用。在微服务应用中：


demo-service-xxx 是我基于一个早期的框架设计的，你可以看到我们以往设计开发的原始状态；
而 demo-service2-xxx 是我需要重点讲解的基于 DDD 的微服务设计。


其中，demo-service2-support 是这个框架的核心，即底层技术中台，而其他都是演示对它的具体应用。

单 Controller 的设计实现

与以往不同，在整个系统中只有几个 Controller，并下沉到了底层技术中台 demo-service2-support 中，它们包括以下几部分。


OrmController：用于增删改操作，以及基于 key 值的 load、get 操作，它们通常基于DDD 进行设计。
QueryController：用于基于 SQL 语句形成的查询分析报表，它们通常不基于 DDD 进行设计，但查询结果会形成领域对象，并基于 DDD 进行数据补填。
其他 Controller，用于如 ExcelController 等特殊的操作，是继承以上两个类的功能扩展。


OrmController 接收诸如 orm/{bean}/{method} 的请求，bean 是配置在 Spring 中的 bean，method 是 bean 中要调用的方法。由于这是一个基础框架，没有限定前端可以调用哪些方法，因此实际项目需要在此之上增加权限校验。该方法既可以接收 GET 方法，也可以接收 POST 方法，因此其他的参数可以根据 GET/POST 各自的方式进行传递。

这里的 bean 对应的是后台的 Service。Service 的编写要求所有的方法，如果需要使用领域对象必须放在第一个参数上。如果第一个参数是简单的数字、字符串、日期等类型，就不是领域对象，否则就作为领域对象，依次从前端上传的 JSON 中获取相应的数据予以填充。这里暂时不支持集合，也不支持具有继承关系的领域对象，待我日后完善。判定代码如下：

 /**

  * check a parameter whether is a value object.

  * @param clazz

  * @return yes or no

  * @throws IllegalAccessException 

  * @throws InstantiationException 

  */

 private boolean isValueObject(Class<?> clazz) {

  if(clazz==null) return false;

  if(clazz.equals(long.class)||clazz.equals(int.class)||

    clazz.equals(double.class)||clazz.equals(float.class)||

    clazz.equals(short.class)) return false;

  if(clazz.isInterface()) return false;

  if(Number.class.isAssignableFrom(clazz)) return false;

  if(String.class.isAssignableFrom(clazz)) return false;

  if(Date.class.isAssignableFrom(clazz)) return false;

  if(Collection.class.isAssignableFrom(clazz)) return false;

  return true;

 }


这里的开发规范除了要求 Service 的所有方法中的领域对象放第一个参数，还要求前端的 JSON 与领域对象中的属性一致，这样才能完成自动转换，而不需要为每个模块编写 Controller。

QueryController 接收诸如 query/{bean} 的请求，这里的 bean 依然是 Spring 中配置的bean。同样，该方法也是既可以接收 GET 方法，也可以接收 POST 方法，并用各自的方式传递查询所需的参数。

如果该查询需要分页，那么在传递查询参数以外，还要传递 page（第几页）与 size（每页多少条记录）。第一次查询时，除了分页，还会计算 count 并返回前端。这样，在下次分页查询时，将 count 也作为参数传递，将不再计算 count，从而提升查询效率。此外，这里还将提供求和功能，敬请期待。

单 Dao 的设计实现

以往系统设计的硬伤在于一头一尾：Controller 与 Dao。它既要为每个模块编写大量代码，也使得系统设计非常不 DDD，令日后的变更维护成本巨大。因此，我在大量系统设计问题分析的基础上，提出了单 Controller 与单 Dao 的设计思路。前面讲解了单 Controller 的设计，现在来看一看单 Dao 的设计。

诚然，当今的主流是使用注解。然而，注解的使用存在诸多的问题。


首先，它会带来业务代码与技术框架的依赖，因此当在 Service 中加入注解时，就不得不与 Spring、Springcloud 耦合，使得日后转型其他技术框架困难重重。
此外，注解往往适用于一对一、多对一的场景，而一对多、多对多的场景往往非常麻烦。而本框架存在大量一对多、多对多的场景，因此我建议你还是回归到 XML 的配置方式。


在项目中的所有 Service 都要有一个 BasicDao 的属性变量，例如：

public class CustomerServiceImpl implements CustomerService {

 private BasicDao dao;

 /**

  * @return the dao

  */

 public BasicDao getDao() {

  return dao;

 }

 /**

  * @param dao the dao to set

  */

 public void setDao(BasicDao dao) {

  this.dao = dao;

 }

    ...

}


接着，在 applicationContext-orm.xml 中，配置业务操作的 Service：

<?xml version="1.0" encoding="UTF-8"?>

<beans xmlns="http://www.springframework.org/schema/beans" ...>

 <description>The application context for orm</description>

 <bean id="customer" class="com.demo2.trade.service.impl.CustomerServiceImpl">

  <property name="dao" ref="repositoryWithCache"></property>

 </bean>

 <bean id="product" class="com.demo2.trade.service.impl.ProductServiceImpl">

  <property name="dao" ref="repositoryWithCache"></property>

 </bean>

 <bean id="supplier" class="com.demo2.trade.service.impl.SupplierServiceImpl">

  <property name="dao" ref="basicDao"></property>

 </bean>

 <bean id="order" class="com.demo2.trade.service.impl.OrderServiceImpl">

  <property name="dao" ref="repository"></property>

 </bean>

</beans>


这里可以看到，每个 Service 都要注入 Dao，但可以根据需求注入不同的 Dao。


如果该 Service 是纯贫血模型，那么注入 BasicDao 就可以了。
如果采用了充血模型，包含了一些聚合的操作，那么注入 repository 从而实现仓库与工厂的功能。
但如果还希望该仓库与工厂能提供缓存的功能，那么就注入 repositoryWithCache。


例如，在以上案例中：


SupplierService 实现的是非常简单的功能，注入 BasicDao 就可以了；
OrderService 实现了订单与明细的聚合，但数据量大不适合使用缓存，所以注入 repository；
CustomerService 实现了用户与地址的聚合，并且需要缓存，所以注入 repositoryWithCache；
ProductService 虽然没有聚合，但在查询产品时需要补填供应商，因此也注入repositoryWithCache。


这里需要注意，是否使用缓存，也可以在日后的运维过程中，让运维人员通过修改配置去决定，从而提高系统的可维护性。

完成配置以后，核心是将领域建模映射成程序设计的模型。开发人员首先编写各个领域对象。譬如，产品要关联供应商，那么在增加 supplier_id 的同时，还要增加一个 Supplier 的属性：

public class Product extends Entity<Long> {

 private static final long serialVersionUID = 7149822235159719740L;

 private Long id;

 private String name;

 private Double price;

 private String unit;

 private Long supplier_id;

 private String classify;

 private Supplier supplier;

    ...

}


注意，在本框架中的每个领域对象都必须要实现 Entity 这个接口，系统才知道你的主键是哪个。

接着，配置 vObj.xml，将领域对象与数据库对应起来：

<?xml version="1.0" encoding="UTF-8"?>

<vobjs>

  <vo class="com.demo2.trade.entity.Customer" tableName="Customer">

    <property name="id" column="id" isPrimaryKey="true"></property>

    <property name="name" column="name"></property>

    <property name="sex" column="sex"></property>

    <property name="birthday" column="birthday"></property>

    <property name="identification" column="identification"></property>

    <property name="phone_number" column="phone_number"></property>

    <join name="addresses" joinKey="customer_id" joinType="oneToMany" isAggregation="true" class="com.demo2.trade.entity.Address"></join>

  </vo>

  <vo class="com.demo2.trade.entity.Address" tableName="Address">

   <property name="id" column="id" isPrimaryKey="true"></property>

   <property name="customer_id" column="customer_id"></property>

   <property name="country" column="country"></property>

   <property name="province" column="province"></property>

   <property name="city" column="city"></property>

   <property name="zone" column="zone"></property>

   <property name="address" column="address"></property>

   <property name="phone_number" column="phone_number"></property>

  </vo>

  <vo class="com.demo2.trade.entity.Product" tableName="Product">

    <property name="id" column="id" isPrimaryKey="true"></property>

    <property name="name" column="name"></property>

    <property name="price" column="price"></property>

    <property name="unit" column="unit"></property>

    <property name="classify" column="classify"></property>

    <property name="supplier_id" column="supplier_id"></property>

    <join name="supplier" joinKey="supplier_id" joinType="manyToOne" class="com.demo2.trade.entity.Supplier"></join>

  </vo>

  <vo class="com.demo2.trade.entity.Supplier" tableName="Supplier">

    <property name="id" column="id" isPrimaryKey="true"></property>

    <property name="name" column="name"></property>

  </vo>

  <vo class="com.demo2.trade.entity.Order" tableName="Order">

   <property name="id" column="id" isPrimaryKey="true"></property>

   <property name="customer_id" column="customer_id"></property>

   <property name="address_id" column="address_id"></property>

   <property name="amount" column="amount"></property>

   <property name="order_time" column="order_time"></property>

   <property name="flag" column="flag"></property>

   <join name="customer" joinKey="customer_id" joinType="manyToOne" class="com.demo2.trade.entity.Customer"></join>

   <join name="address" joinKey="address_id" joinType="manyToOne" class="com.demo2.trade.entity.Address"></join>

   <join name="orderItems" joinKey="order_id" joinType="oneToMany" isAggregation="true" class="com.demo2.trade.entity.OrderItem"></join>

  </vo>

  <vo class="com.demo2.trade.entity.OrderItem" tableName="OrderItem">

   <property name="id" column="id" isPrimaryKey="true"></property>

   <property name="order_id" column="order_id"></property>

   <property name="product_id" column="product_id"></property>

   <property name="quantity" column="quantity"></property>

   <property name="price" column="price"></property>

   <property name="amount" column="amount"></property>

   <join name="product" joinKey="product_id" joinType="manyToOne" class="com.demo2.trade.entity.Product"></join>

  </vo>

</vobjs>


注意，在这里，所有用到 join 或 ref 标签的领域对象，其 Service 都必须使用 repository 或repositoryWithCache，以实现数据的自动补填，或者有聚合的地方实现聚合的操作，而注入 BasicDao 是无法实现这些操作的。

此外，各属性中的 name 配置的是该领域对象私有属性变量的名字，而不是 GET 方法的名字。例如，OrderItem 中配置的是 product_id，而不是 productId，并且该名字必须与数据库字段一致（这是 MyBatis 的要求，我也很无奈）。

有了以上的配置，就可以轻松实现 Service 对数据库的操作，以及 DDD 中那些烦琐的缓存、仓库、工厂、聚合、补填等操作。通过底层技术中台的封装，上层业务开发人员就可以专注于业务理解、领域建模，以及基于领域模型的业务开发，让 DDD 能更好、更快、风险更低地落地到实际项目中。

总结

本讲为你讲解了我设计的支持 DDD 的技术中台的设计开发思路，包括如何设计单 Controller、如何设计单 Dao，以及它们在项目中的应用。

下一讲我将更进一步讲解该框架如何设计单 Service 进行查询、通用仓库与通用工厂的设计，以及它们对微服务架构的支持。

点击 GitHub 链接，查看源码。

                        
                        
                            