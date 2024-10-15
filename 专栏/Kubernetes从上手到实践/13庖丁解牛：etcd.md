
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            13 庖丁解牛：etcd
                            整体概览

+----------------------------------------------------------+          
| Master                                                   |          
|              +-------------------------+                 |          
|     +------->|        API Server       |<--------+       |          
|     |        |                         |         |       |          
|     v        +-------------------------+         v       |          
|   +----------------+     ^      +--------------------+   |          
|   |                |     |      |                    |   |          
|   |   Scheduler    |     |      | Controller Manager |   |          
|   |                |     |      |                    |   |          
|   +----------------+     v      +--------------------+   |          
| +------------------------------------------------------+ |          
| |                                                      | |          
| |                Cluster state store                   | |          
| |                                                      | |          
| +------------------------------------------------------+ |          
+----------------------------------------------------------+          


在第 3 节《宏观认识：整体架构》 中，我们也认识到了 etcd 的存在，知道了 Master 是 K8S 是集群的大脑，而 etcd 则是大脑的核心。为什么这么说？本节我们一同来看看 etcd 为何如此重要。

etcd 是什么

先摘录官方文档的一句说明:


etcd is a consistent distributed key-value store. Mainly used as a separate coordination service, in distributed systems. And designed to hold small amounts of data that can fit entirely in memory.


etcd 是由 CoreOS 团队发起的一个分布式，强一致的键值存储。它用 Go 语言编写，使用 Raft 协议作为一致性算法。多数情况下会用于分布式系统中的服务注册发现，或是用于存储系统的关键数据。

etcd 有什么作用

etcd 在 K8S 中，最主要的作用便是其高可用，强一致的键值存储以及监听机制。

在 kube-apiserver 收到对应请求经过一系列的处理后，最终如果是集群所需要存储的数据，便会存储至 etcd 中。主部分主要是集群状态信息和元信息。

我们来实际操作 K8S 集群中的 etcd 看下真实情况。


查找集群中的 etcd Pod


# 默认集群中的 etcd 会放到 kube-system Namespace 中
master $ kubectl -n kube-system get pods | grep etcd
etcd-master                      1/1       Running   0          1h



进入该 Pod 并查看 etcd 集群的 member


master $ kubectl -n kube-system exec -it etcd-master sh
/ # ETCDCTL_API=3 etcdctl --key=/etc/kubernetes/pki/etcd/server.key  --cert=/etc/kubernetes/pki/etcd/server.crt  --cacert=/etc/kubernetes/pki/etcd/ca.crt member list
a874c87fd42044f, started, master, https://127.0.0.1:2380, https://127.0.0.1:2379


这里由于在 K8S 1.11.3 中默认使用的是 etcd 3.2 版本，所以需要加入 ETCDCTL_API=3 的环境变量，且 etcd 从 2 到 3 很明显的一个变化也是使用上的变化，在 2 中是 HTTP 接口的。

我们通过传递证书等相关参数进去，完成校验，查看 member 。


查看存储的元信息


etcd 中存储的 K8S 集群元信息基本都是 /registry 下，我们可通过下面的方式进行查看

/ # ETCDCTL_API=3 etcdctl --key=/etc/kubernetes/pki/etcd/server.key  --cert=/etc/kubernetes/pki/etcd/server.crt  --cacert=/etc/kubernetes/pki/etcd/ca.crt get /registry --prefix --keys-only
/registry/apiregistration.k8s.io/apiservices/v1.
/registry/clusterroles/system:aggregate-to-admin
/registry/configmaps/kube-public/cluster-info
/registry/masterleases/172.17.0.53
/registry/namespaces/default
/registry/namespaces/kube-public
/registry/namespaces/kube-system
/registry/pods/kube-system/etcd-master
/registry/pods/kube-system/kube-apiserver-master
/registry/ranges/serviceips
/registry/ranges/servicenodeports
...
# 篇幅原因，删掉了很多输出


可以看到有各种类型的资源。我们直接以 Namespaces 为例。


查看 Namespaces 信息


/ # ETCDCTL_API=3 etcdctl --key=/etc/kubernetes/pki/etcd/server.key  --cert=/etc/kubernetes/pki/etcd/server.crt  --cacert=/etc/kubernetes/pki/etcd/ca.crt get /registry/namespaces --prefix --keys-only
/registry/namespaces/default

/registry/namespaces/kube-public

/registry/namespaces/kube-system



使用 kubectl 创建名为 moelove 的 Namespaces


master $ kubectl create  ns moelove
namespace/moelove created



查看 Namespaces 信息


/ # ETCDCTL_API=3 etcdctl --key=/etc/kubernetes/pki/etcd/server.key  --cert=/etc/kubernetes/pki/etcd/server.crt  --cacert=/etc/kubernetes/pki/etcd/ca.crt get /registry/namespaces --prefix --keys-only
/registry/namespaces/default

/registry/namespaces/kube-public

/registry/namespaces/kube-system

/registry/namespaces/moelove


发现刚创建的 moelove 的 Namespaces 已经在 etcd 中了。

etcd 是如何被使用的

首先，在 staging/src/k8s.io/apiserver/pkg/server/options/etcd.go 中，存在一处声明：

var storageTypes = sets.NewString(
	storagebackend.StorageTypeUnset,
	storagebackend.StorageTypeETCD2,
	storagebackend.StorageTypeETCD3,
)


在 1.13 发布时，etcd 2 的相关代码已经移除，其中就包含上面声明中的 storagebackend.StorageTypeETCD2

这里是在过渡期间为了同时兼容 etcd 2 和 3 而增加的。

我们来看下实际对各类资源的操作，还是以对 Namespace 的操作为例：代码在 pkg/registry/core/namespace/storage/storage.go 中，

比如，Get：

func (r *REST) Get(ctx context.Context, name string, options *metav1.GetOptions) (runtime.Object, error) {
	return r.store.Get(ctx, name, options)
}


而 REST 则是这样定义的：

type REST struct {
	store  *genericregistry.Store
	status *genericregistry.Store
}


通过 REST 实现了一个 RESTStorage，实际使用时，也是调用了 staging/src/k8s.io/apiserver/pkg/registry/generic/registry/store.go 对接口的封装。

func (e *Store) Get(ctx context.Context, name string, options *metav1.GetOptions) (runtime.Object, error) {
	obj := e.NewFunc()
	key, err := e.KeyFunc(ctx, name)
	if err != nil {
		return nil, err
	}
	if err := e.Storage.Get(ctx, key, options.ResourceVersion, obj, false); err != nil {
		return nil, storeerr.InterpretGetError(err, e.qualifiedResourceFromContext(ctx), name)
	}
	if e.Decorator != nil {
		if err := e.Decorator(obj); err != nil {
			return nil, err
		}
	}
	return obj, nil
}


在该处实现了对各类基本方法的封装，各种资源类型都会统一去调用。而更上层则是对 storagebackend 的统一封装，最终会调用 etcd 客户端的实现完成想对应的操作，这里就不再多展开了。

总结

在本节中，我们介绍了 etcd 以及它在 K8S 中的作用以及如何使用。我们还介绍了如何通过 etcdctl 工具去操作 etcd。

在某些极端情况下，也许你需要通过直接操作 etcd 集群去变更数据，这里没有介绍所有的操作命令，感兴趣的可以自行通过下方的链接看官方文档进行学习。

但通常情况下，不建议直接操作 etcd ，除非你已经明确自己在做什么。

另外，由于 etcd 集群使用 Raft 一致性算法，通常情况下 etcd 集群需要部署奇数个节点，如 3，5，7 等。etcd 集群维护也相对容易，很容易可以做成高可用集群。（这也是保障 K8S 集群高可用的重要一环）

学习了 etcd 之后，下节我们来学习同样很重要的一个组件 Controller Manager，学习它是如何保障集群符合预期状态的。

参考链接


What is etcd


                        
                        
                            