
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            10  ORM 集成：如何使用 Spring Data JPA 访问关系型数据库？
                            在前面的课程中，我们详细介绍了如何使用 Spring 所提供的 JdbcTemplate 模板工具类实现数据访问的实现方法。相较 JDBC 所提供的原生 API，JdbcTemplate 做了一层封装，大大简化了数据的操作过程。而在 09 讲中，我们又进一步引入了 Spring Data 框架，可以说 Spring Data 框架是基于 JdbcTemplate 上另一层更高级的封装。

今天，我们将基于 Spring Data 中的 Spring Data JPA 组件介绍如何集成 ORM 框架实现关系型数据库访问。

引入 Spring Data JPA

如果你想在应用程序中使用 Spring Data JPA，首先需要在 pom 文件中引入 spring-boot-starter-data-jpa 依赖，如下代码所示：

<dependency>

     <groupId>org.springframework.boot</groupId>

     <artifactId>spring-boot-starter-data-jpa</artifactId>

</dependency>


在介绍这一组件的使用方法之前，我们有必要对 JPA 规范进行一定的了解。

JPA 全称是 JPA Persistence API，即 Java 持久化 API，它是一个 Java 应用程序接口规范，用于充当面向对象的领域模型和关系数据库系统之间的桥梁，属于一种 ORM（Object Relational Mapping，对象关系映射）技术。

JPA 规范中定义了一些既定的概念和约定，集中包含在 javax.persistence 包中，常见的如对实体（Entity）定义、实体标识定义、实体与实体之间的关联关系定义，以及 09 讲中介绍的 JPQL 定义等，关于这些定义及其使用方法，一会儿我们会详细展开说明。

与 JDBC 规范一样，JPA 规范也有一大批实现工具和框架，极具代表性的如老牌的 Hibernate 及今天我们将介绍的 Spring Data JPA。

为了演示基于 Spring Data JPA 的整个开发过程，我们将在 SpringCSS 案例中专门设计和实现一套独立的领域对象和 Repository，接下来我们一起来看下。

实体类注解

我们知道 order-service 中存在两个主要领域对象，即 Order 和 Goods。为了与前面课时介绍的领域对象有所区分，本节课我们重新创建两个领域对象，分别命名为 JpaOrder 和 JpaGoods，它们就是 JPA 规范中的实体类。

我们先来看下相对简单的 JpaGoods，这里我们把 JPA 规范的相关类的引用罗列在了一起，JpaGoods 定义如下代码所示：

import javax.persistence.Entity;

import javax.persistence.GeneratedValue;

import javax.persistence.GenerationType;

import javax.persistence.Id;

import javax.persistence.Table;

 

@Entity

@Table(name="goods")

public class JpaGoods {

    

    @Id

    @GeneratedValue(strategy = GenerationType.IDENTITY)

    private Long id;    

    private String goodsCode;

    private String goodsName;

    private Float price;    

    //省略 getter/setter

}


JpaGoods 中使用了 JPA 规范中用于定义实体的几个注解：最重要的 @Entity 注解、用于指定表名的 @Table 注解、用于标识主键的 @Id 注解，以及用于标识自增数据的 @GeneratedValue 注解，这些注解都比较直白，在实体类上直接使用即可。

接下来，我们看下比较复杂的 JpaOrder，定义如下代码所示：

@Entity

@Table(name="`order`")

public class JpaOrder implements Serializable {

    private static final long serialVersionUID = 1L;

    

    @Id

    @GeneratedValue(strategy = GenerationType.IDENTITY)

    private Long id;

    private String orderNumber;

    private String deliveryAddress;

 

    @ManyToMany(targetEntity=JpaGoods.class)

    @JoinTable(name = "order_goods", joinColumns = @JoinColumn(name = "order_id", referencedColumnName = "id"), inverseJoinColumns = @JoinColumn(name = "goods_id", referencedColumnName = "id"))

    private List<JpaGoods> goods = new ArrayList<>();

 

    //省略 getter/setter

}


这里除了引入了常见的一些注解，还引入了 @ManyToMany 注解，它表示 order 表与 goods 表中数据的关联关系。

在JPA 规范中，共提供了 one-to-one、one-to-many、many-to-one、many-to-many 这 4 种映射关系，它们分别用来处理一对一、一对多、多对一，以及多对多的关联场景。

针对 order-service 这个业务场景，我们设计了一张 order_goods 中间表存储 order 与 goods 表中的主键关系，且使用了 @ManyToMany 注解定义 many-to-many 这种关联关系，也使用了 @JoinTable 注解指定 order_goods 中间表，并通过 joinColumns 和 inverseJoinColumns 注解分别指定中间表中的字段名称以及引用两张主表中的外键名称。

定义 Repository

定义完实体对象后，我们再来提供 Repository 接口，这一步的操作非常简单，OrderJpaRepository 的定义如下代码所示：

@Repository("orderJpaRepository")

public interface OrderJpaRepository extends JpaRepository<JpaOrder, Long>

{

}


从上面代码中我们发现，OrderJpaRepository 是一个继承了 JpaRepository 接口的空接口，基于 09 讲的介绍，我们知道 OrderJpaRepository 实际上已经具备了访问数据库的基本 CRUD 功能。

使用 Spring Data JPA 访问数据库

有了上面定义的 JpaOrder 和 JpaGoods 实体类，以及 OrderJpaRepository 接口，我们已经可以实现很多操作了。

比如我们想通过 Id 获取 Order 对象，首先可以通过构建一个 JpaOrderService 直接注入 OrderJpaRepository 接口，如下代码所示：

@Service

public class JpaOrderService {

 

    @Autowired

    private OrderJpaRepository orderJpaRepository;

 

    public JpaOrder getOrderById(Long orderId) {

 

        return orderJpaRepository.getOne(orderId);

    }

}


然后，我们再通过构建一个 Controller 类嵌入上述方法，并通过 HTTP 请求查询 Id 为 1 的 JpaOrder 对象，获得的结果如下代码所示：

{

    "id": 1,

    "orderNumber": "Order10001",

    "deliveryAddress": "test_address1",

    "goods": [

        {

            "id": 1,

            "goodsCode": "GoodsCode1",

            "goodsName": "GoodsName1",

            "price": 100.0

        },

        {

            "id": 2,

            "goodsCode": "GoodsCode2",

            "goodsName": "GoodsName2",

            "price": 200.0

        }

    ]

}


请注意，这里我们不仅获取了 order 表中的订单基础数据，还同时获取了 goods 表中的商品数据，这种效果是如何实现的呢？是因为在 JpaOrder 对象中，我们添加了 @ManyToMany 注解，该注解会自动从 order_goods 表中获取商品主键信息，并从 goods 表中获取商品详细信息。

了解了使用 Spring Data JPA 实现关系型数据库访问的过程，并对比《数据访问：如何使用 JdbcTemplate 访问关系型数据库？》中通过 JdbcTemplate 获取这部分数据的实现过程，我们发现使用 Spring Data JPA 更简单。

在多样化查询实现过程中，我们不仅可以使用 JpaRepository 中默认集成的各种 CRUD 方法，还可以使用 09 讲中介绍的 @Query 注解、方法名衍生查询等。今天，我们还将同时引入 QueryByExample 和 Specification 这两种机制来丰富多样化查询方式。

使用 @Query 注解

使用 @Query 注解实现查询的示例如下代码所示：

@Repository("orderJpaRepository")

public interface OrderJpaRepository extends JpaRepository<JpaOrder, Long>

{

 

    @Query("select o from JpaOrder o where o.orderNumber = ?1")

    JpaOrder getOrderByOrderNumberWithQuery(String orderNumber);

}


这里，我们使用了 JPQL 根据 OrderNumber 查询订单信息。JPQL 的语法与 SQL 语句非常类似，09 讲中我们对 JPQL 进行了讨论，这里我们不再赘述，你可以前往回顾。

说到 @Query 注解，JPA 中还提供了一个 @NamedQuery 注解对 @Query 注解中的语句进行命名。@NamedQuery 注解的使用方式如下代码所示：

@Entity

@Table(name = "`order`")

@NamedQueries({ @NamedQuery(name = "getOrderByOrderNumberWithQuery", query = "select o from JpaOrder o where o.orderNumber = ?1") })

public class JpaOrder implements Serializable {


在上述示例中，我们在实体类 JpaOrder 上添加了一个 @NamedQueries 注解，该注解可以将一批 @NamedQuery 注解整合在一起使用。同时，我们还使用了 @NamedQuery 注解定义了一个“getOrderByOrderNumberWithQuery”查询，且指定了对应的 JPQL 语句。

如果你想使用这个命名查询，在 OrderJpaRepository 中定义与该命名一致的方法即可。

使用方法名衍生查询

使用方法名衍生查询是最方便的一种自定义查询方式，在这过程中开发人员唯一需要做的就是在 JpaRepository 接口中定义一个符合查询语义的方法。

比如我们希望通过 OrderNumber 查询订单信息，那么可以提供如下代码所示的接口定义：

@Repository("orderJpaRepository")

public interface OrderJpaRepository extends JpaRepository<JpaOrder, Long>

{

    JpaOrder getOrderByOrderNumber(String orderNumber);

}


通过 getOrderByOrderNumber 方法后，我们就可以自动根据 OrderNumber 获取订单详细信息了。

使用 QueryByExample 机制

接下来我们将介绍另一种强大的查询机制，即 QueryByExample（QBE）机制。

针对 JpaOrder 对象，假如我们希望根据 OrderNumber 及 DeliveryAddress 中的一个或多个条件进行查询，按照方法名衍生查询的方式构建查询方法后，得到如下代码所示的方法定义：

List<JpaOrder> findByOrderNumberAndDeliveryAddress (String 

	orderNumber, String deliveryAddress);


如果查询条件中使用的字段非常多，上面这个方法名可能非常长，且还需要设置一批参数，这种查询方法定义显然存在缺陷。

因为不管查询条件有多少个，我们都需要把所有参数进行填充，哪怕部分参数并没有被用到。而且，如果将来我们需要再添加一个新的查询条件，该方法必须做调整，从扩展性上讲也存在设计缺陷。为了解决这些问题，我们便可以引入 QueryByExample 机制。

QueryByExample 可以翻译为按示例查询，是一种用户友好的查询技术。它允许我们动态创建查询，且不需要编写包含字段名称的查询方法，也就是说按示例查询不需要使用特定的数据库查询语言来编写查询语句。

从组成结构上讲，QueryByExample 包括 Probe、ExampleMatcher 和 Example 这三个基本组件。其中， Probe 包含对应字段的实例对象，ExampleMatcher 携带有关如何匹配特定字段的详细信息，相当于匹配条件，Example 则由 Probe 和 ExampleMatcher 组成，用于构建具体的查询操作。

现在，我们基于 QueryByExample 机制重构根据 OrderNumber 查询订单的实现过程。

首先，我们需要在 OrderJpaRepository 接口的定义中继承 QueryByExampleExecutor 接口，如下代码所示：

@Repository("orderJpaRepository")

public interface OrderJpaRepository extends JpaRepository<JpaOrder, Long>, QueryByExampleExecutor<JpaOrder> {


然后，我们在 JpaOrderService 中实现如下代码所示的 getOrderByOrderNumberByExample 方法：

public JpaOrder getOrderByOrderNumberByExample(String orderNumber) {

        JpaOrder order = new JpaOrder();

        order.setOrderNumber(orderNumber);

 

        ExampleMatcher matcher = ExampleMatcher.matching().withIgnoreCase()

                .withMatcher("orderNumber", GenericPropertyMatchers.exact()).withIncludeNullValues();

 

        Example<JpaOrder> example = Example.of(order, matcher);

 

        return orderJpaRepository.findOne(example).orElse(new JpaOrder());

}


上述代码中，我们首先构建了一个 ExampleMatcher 对象用于初始化匹配规则，然后通过传入一个 JpaOrder 对象实例和 ExampleMatcher 实例构建了一个 Example 对象，最后通过 QueryByExampleExecutor 接口中的 findOne() 方法实现了 QueryByExample 机制。

使用 Specification 机制

本节课中，最后我们想介绍的查询机制是 Specification 机制。

先考虑这样一种场景，比如我们需要查询某个实体，但是给定的查询条件不固定，此时该怎么办？这时我们通过动态构建相应的查询语句即可，而在 Spring Data JPA 中可以通过 JpaSpecificationExecutor 接口实现这类查询。相比使用 JPQL 而言，使用 Specification 机制的优势是类型安全。

继承了 JpaSpecificationExecutor 的 OrderJpaRepository 定义如下代码所示：

@Repository("orderJpaRepository")

public interface OrderJpaRepository extends JpaRepository<JpaOrder, Long>,     JpaSpecificationExecutor<JpaOrder>{


对于 JpaSpecificationExecutor 接口而言，它背后使用的就是 Specification 接口，且 Specification 接口核心方法就一个，我们可以简单地理解该接口的作用就是构建查询条件，如下代码所示：

Predicate toPredicate(Root<T> root, CriteriaQuery<?> query, CriteriaBuilder criteriaBuilder);


其中 Root 对象代表所查询的根对象，我们可以通过 Root 获取实体的属性，CriteriaQuery 代表一个顶层查询对象，用来实现自定义查询，而 CriteriaBuilder 用来构建查询条件。

基于 Specification 机制，我们同样对根据 OrderNumber 查询订单的实现过程进行重构，重构后的 getOrderByOrderNumberBySpecification 方法如下代码所示：

public JpaOrder getOrderByOrderNumberBySpecification(String orderNumber) {

        JpaOrder order = new JpaOrder();

        order.setOrderNumber(orderNumber);

 

        @SuppressWarnings("serial")

        Specification<JpaOrder> spec = new Specification<JpaOrder>() {

            @Override

            public Predicate toPredicate(Root<JpaOrder> root, CriteriaQuery<?> query, CriteriaBuilder cb) {

                Path<Object> orderNumberPath = root.get("orderNumber");

              

                Predicate predicate = cb.equal(orderNumberPath, orderNumber);

                return predicate;

            }

        };

 

        return orderJpaRepository.findOne(spec).orElse(new JpaOrder());     

}


从上面示例中可以看到，在 toPredicate 方法中，首先我们从 root 对象中获取了“orderNumber”属性，然后通过 cb.equal 方法将该属性与传入的 orderNumber 参数进行了比对，从而实现了查询条件的构建过程。

小结与预告

10 讲中，我们主要对通过 Spring Data JPA 进行数据操作的方法和技巧做了一一介绍。

在 Spring Boot 中，我极力推荐使用 Spring Data JPA 实现对关系型数据库访问，因为它不仅具有 ORM 框架的通用功能，同时还添加了 QueryByExample 和 Specification 机制等扩展性功能，应用上简单而高效。

                        
                        
                            