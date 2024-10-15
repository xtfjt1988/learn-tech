
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            46 多个 ThreadLocal 在 Thread 中的 threadlocals 里是怎么存储的？
                            本课时我们主要分析一下在 Thread 中多个 ThreadLocal 是怎么存储的。

Thread、 ThreadLocal 及 ThreadLocalMap 三者之间的关系

在讲解本课时之前，先要搞清楚 Thread、 ThreadLocal 及 ThreadLocalMap 三者之间的关系。我们用最直观、最容易理解的图画的方式来看看它们三者的关系：


我们看到最左下角的 Thread 1，这是一个线程，它的箭头指向了  ThreadLocalMap 1，其要表达的意思是，每个 Thread 对象中都持有一个 ThreadLocalMap 类型的成员变量，在这里 Thread 1 所拥有的成员变量就是 ThreadLocalMap 1。

而这个 ThreadLocalMap 自身类似于是一个 Map，里面会有一个个 key value 形式的键值对。那么我们就来看一下它的 key 和 value 分别是什么。可以看到这个表格的左侧是 ThreadLocal 1、ThreadLocal 2…… ThreadLocal n，能看出这里的 key 就是 ThreadLocal 的引用。

而在表格的右侧是一个一个的 value，这就是我们希望 ThreadLocal 存储的内容，例如 user 对象等。

这里需要重点看到它们的数量对应关系：一个 Thread 里面只有一个ThreadLocalMap ，而在一个 ThreadLocalMap 里面却可以有很多的 ThreadLocal，每一个 ThreadLocal 都对应一个 value。因为一个 Thread 是可以调用多个 ThreadLocal 的，所以 Thread 内部就采用了 ThreadLocalMap 这样 Map 的数据结构来存放 ThreadLocal 和 value。

通过这张图片，我们就可以搞清楚 Thread、 ThreadLocal 及 ThreadLocalMap 三者在宏观上的关系了。

源码分析

知道了它们的关系之后，我们再来进行源码分析，来进一步地看到它们内部的实现。

get 方法

首先我们来看一下 get 方法，源码如下所示：

public T get() {

    //获取到当前线程

    Thread t = Thread.currentThread();

    //获取到当前线程内的 ThreadLocalMap 对象，每个线程内都有一个 ThreadLocalMap 对象

    ThreadLocalMap map = getMap(t);

    if (map != null) {

        //获取 ThreadLocalMap 中的 Entry 对象并拿到 Value

        ThreadLocalMap.Entry e = map.getEntry(this);

        if (e != null) {

            @SuppressWarnings("unchecked")

            T result = (T)e.value;

            return result;

        }

    }

    //如果线程内之前没创建过 ThreadLocalMap，就创建

    return setInitialValue();

}


这是 ThreadLocal 的 get 方法，可以看出它利用了 Thread.currentThread 来获取当前线程的引用，并且把这个引用传入到了 getMap 方法里面，来拿到当前线程的 ThreadLocalMap。

然后就是一个 if ( map != null ) 条件语句，那我们先来看看 if (map == null) 的情况，如果 map == null，则说明之前这个线程中没有创建过 ThreadLocalMap，于是就去调用 setInitialValue 来创建；如果 map != null，我们就应该通过 this 这个引用（也就是当前的 ThreadLocal 对象的引用）来获取它所对应的 Entry，同时再通过这个 Entry 拿到里面的 value，最终作为结果返回。

值得注意的是，这里的 ThreadLocalMap 是保存在线程 Thread 类中的，而不是保存在 ThreadLocal 中的。

getMap 方法

下面我们来看一下 getMap 方法，源码如下所示：

ThreadLocalMap getMap(Thread t) {

    return t.threadLocals;

}


可以看到，这个方法很清楚地表明了 Thread 和 ThreadLocalMap 的关系，可以看出 ThreadLocalMap 是线程的一个成员变量。这个方法的作用就是获取到当前线程内的 ThreadLocalMap 对象，每个线程都有 ThreadLocalMap 对象，而这个对象的名字就叫作 threadLocals，初始值为 null，代码如下：

ThreadLocal.ThreadLocalMap threadLocals = null;


set 方法

下面我们再来看一下 set 方法，源码如下所示：

public void set(T value) {

    Thread t = Thread.currentThread();

    ThreadLocalMap map = getMap(t);

    if (map != null)

        map.set(this, value);

    else

        createMap(t, value);

}


set 方法的作用是把我们想要存储的 value 给保存进去。可以看出，首先，它还是需要获取到当前线程的引用，并且利用这个引用来获取到 ThreadLocalMap ；然后，如果 map == null 则去创建这个 map，而当 map != null 的时候就利用 map.set 方法，把 value 给 set 进去。

可以看出，map.set(this, value)  传入的这两个参数中，第一个参数是 this，就是当前 ThreadLocal 的引用，这也再次体现了，在 ThreadLocalMap 中，它的 key 的类型是 ThreadLocal；而第二个参数就是我们所传入的 value，这样一来就可以把这个键值对保存到 ThreadLocalMap 中去了。

ThreadLocalMap 类，也就是 Thread.threadLocals

下面我们来看一下 ThreadLocalMap 这个类，下面这段代码截取自定义在 ThreadLocal 类中的 ThreadLocalMap 类：

static class ThreadLocalMap {

    static class Entry extends WeakReference<ThreadLocal<?>> {

        /** The value associated with this ThreadLocal. */

        Object value;

        Entry(ThreadLocal<?> k, Object v) {

            super(k);

            value = v;

        }

    }

   private Entry[] table;

//...

}


ThreadLocalMap 类是每个线程 Thread 类里面的一个成员变量，其中最重要的就是截取出的这段代码中的 Entry 内部类。在 ThreadLocalMap 中会有一个 Entry 类型的数组，名字叫 table。我们可以把 Entry 理解为一个 map，其键值对为：


键，当前的 ThreadLocal；
值，实际需要存储的变量，比如 user 用户对象或者 simpleDateFormat 对象等。


ThreadLocalMap 既然类似于 Map，所以就和 HashMap 一样，也会有包括 set、get、rehash、resize 等一系列标准操作。但是，虽然思路和 HashMap 是类似的，但是具体实现会有一些不同。

比如其中一个不同点就是，我们知道 HashMap 在面对 hash 冲突的时候，采用的是拉链法。它会先把对象 hash 到一个对应的格子中，如果有冲突就用链表的形式往下链，如下图所示：



但是 ThreadLocalMap 解决 hash 冲突的方式是不一样的，它采用的是线性探测法。如果发生冲突，并不会用链表的形式往下链，而是会继续寻找下一个空的格子。这是 ThreadLocalMap 和 HashMap 在处理冲突时不一样的点。

以上就是本节课的内容。

在本节课中，我们主要分析了 Thread、 ThreadLocal 和 ThreadLocalMap 这三个非常重要的类的关系。用图画的方式表明了它们之间的关系：一个 Thread 有一个 ThreadLocalMap，而 ThreadLocalMap 的 key 就是一个个的 ThreadLocal，它们就是用这样的关系来存储并维护内容的。之后我们对于 ThreadLocal 的一些重要方法进行了源码分析。

                        
                        
                            