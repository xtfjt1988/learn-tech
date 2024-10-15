
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            12 庖丁解牛：kube-apiserver
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


在第 3 节《宏观认识：整体架构》 中，我们初次认识到了 kube-apiserver 的存在（以下内容中将统一称之为 kube-apiserver），知道了它作为集群的统一入口，接收来自外部的信号和请求，并将一些信息存储至 etcd 中。

但这只是一种很模糊的说法，本节我们来具体看看 kube-apiserver 的关键功能以及它的工作原理。

注意：本节所有的源码均以 v1.11.3 为准 commit id a4529464e4629c21224b3d52edfe0ea91b072862。

REST API Server

先来说下 kube-apiserver 作为整个集群的入口，接受外部的信号和请求所应该具备的基本功能。

首先，它对外提供接口，可处理来自客户端（无论我们在用的 kubeclt 或者 curl 或者其他语言实现的客户端）的请求，并作出响应。

在第 5 节搭建集群时，我们提到要先去检查 6443 端口是否被占用。这样检查的原因在于 kube-apiserver 有个 --secure-port 的参数，通过这个参数来配置它将要监听在哪个端口，默认情况下是 6443。

当然，它还有另一个参数 --insecure-port ，这个参数可将 kube-apiserver 绑定到其指定的端口上，且通过该端口访问时无需认证。

在生产环境中，建议将其设置为 0 以禁用该功能。另外，这个参数也已经被标记为废弃，将在之后版本中移除。如果未禁用该功能，建议通过防火墙策略禁止从外部访问该端口。该端口会绑定在 --insecure-bind-address 参数所设置的地址上，默认为 127.0.0.1。

那么 secure 和 insecure 最主要的区别是什么呢？ 这就引出来了 kube-apiserver 作为 API Server 的一个最主要功能：认证。

认证（Authentication）

在第 8 节《认证和授权》中，我们已经讲过认证相关的机制。这里，我们以最简单的获取集群版本号为例。

通常，我们使用 kubeclt version 来获取集群和当前客户端的版本号。

master $ kubectl version
Client Version: version.Info{Major:"1", Minor:"11", GitVersion:"v1.11.3", GitCommit:"a4529464e4629c21224b3d52edfe0ea91b072862", GitTreeState:"clean", BuildDate:"2018-09-09T18:02:47Z", GoVersion:"go1.10.3", Compiler:"gc", Platform:"linux/amd64"}
Server Version: version.Info{Major:"1", Minor:"11", GitVersion:"v1.11.3", GitCommit:"a4529464e4629c21224b3d52edfe0ea91b072862", GitTreeState:"clean", BuildDate:"2018-09-09T17:53:03Z", GoVersion:"go1.10.3", Compiler:"gc", Platform:"linux/amd64"}


获取集群版本号的时候，其实也是向 kube-apiserver 发送了一个请求进行查询的，我们可以通过传递 -v 参数来改变 log level 。

master $ kubectl version -v 8
I1202 03:15:06.360838   13581 loader.go:359] Config loaded from file /root/.kube/config
I1202 03:15:06.362106   13581 round_trippers.go:383] GET https://172.17.0.99:6443/version?timeout=32s
I1202 03:15:06.362130   13581 round_trippers.go:390] Request Headers:
I1202 03:15:06.362139   13581 round_trippers.go:393]     Accept: application/json, */*
I1202 03:15:06.362146   13581 round_trippers.go:393]     User-Agent: kubectl/v1.11.3 (linux/amd64) kubernetes/a452946
I1202 03:15:06.377653   13581 round_trippers.go:408] Response Status: 200 OK in 15 milliseconds
I1202 03:15:06.377678   13581 round_trippers.go:411] Response Headers:
I1202 03:15:06.377686   13581 round_trippers.go:414]     Content-Type: application/json
I1202 03:15:06.377693   13581 round_trippers.go:414]     Content-Length: 263
I1202 03:15:06.377699   13581 round_trippers.go:414]     Date: Sun, 02 Dec 2018 03:15:06 GMT
I1202 03:15:06.379314   13581 request.go:897] Response Body: {
  "major": "1",
  "minor": "11",
  "gitVersion": "v1.11.3",
  "gitCommit": "a4529464e4629c21224b3d52edfe0ea91b072862",
  "gitTreeState": "clean",
  "buildDate": "2018-09-09T17:53:03Z",
  "goVersion": "go1.10.3",
  "compiler": "gc",
  "platform": "linux/amd64"
}
Client Version: version.Info{Major:"1", Minor:"11", GitVersion:"v1.11.3", GitCommit:"a4529464e4629c21224b3d52edfe0ea91b072862", GitTreeState:"clean", BuildDate:"2018-09-09T18:02:47Z", GoVersion:"go1.10.3", Compiler:"gc", Platform:"linux/amd64"}
Server Version: version.Info{Major:"1", Minor:"11", GitVersion:"v1.11.3", GitCommit:"a4529464e4629c21224b3d52edfe0ea91b072862", GitTreeState:"clean", BuildDate:"2018-09-09T17:53:03Z", GoVersion:"go1.10.3", Compiler:"gc", Platform:"linux/amd64"}


通过日志就可以很明显看到，首先会加载 $HOME/.kube/config 下的配置，获的集群地址，进而请求 /version 接口，最后格式化输出。

我们使用 curl 去请求同样的接口：

master $ curl -k https://172.17.0.99:6443/version
{
  "major": "1",
  "minor": "11",
  "gitVersion": "v1.11.3",
  "gitCommit": "a4529464e4629c21224b3d52edfe0ea91b072862",
  "gitTreeState": "clean",
  "buildDate": "2018-09-09T17:53:03Z",
  "goVersion": "go1.10.3",
  "compiler": "gc",
  "platform": "linux/amd64"
}


得到了相同的结果。你可能会有些奇怪，使用 curl -k 相当于忽略了认证的过程，为何还能拿到正确的信息。别急，我们来看下一个例子：

master $ kubectl get ns  -v 8
I1202 03:25:40.607886   16620 loader.go:359] Config loaded from file /root/.kube/config
I1202 03:25:40.608862   16620 loader.go:359] Config loaded from file /root/.kube/config
I1202 03:25:40.611187   16620 loader.go:359] Config loaded from file /root/.kube/config
I1202 03:25:40.622737   16620 loader.go:359] Config loaded from file /root/.kube/config
I1202 03:25:40.623495   16620 round_trippers.go:383] GET https://172.17.0.99:6443/api/v1/namespaces?limit=500
I1202 03:25:40.623650   16620 round_trippers.go:390] Request Headers:
I1202 03:25:40.623730   16620 round_trippers.go:393]     Accept: application/json;as=Table;v=v1beta1;g=meta.k8s.io, application/json
I1202 03:25:40.623820   16620 round_trippers.go:393]     User-Agent: kubectl/v1.11.3 (linux/amd64) kubernetes/a452946
I1202 03:25:40.644280   16620 round_trippers.go:408] Response Status: 200 OK in 20 milliseconds
I1202 03:25:40.644308   16620 round_trippers.go:411] Response Headers:
I1202 03:25:40.644327   16620 round_trippers.go:414]     Content-Type: application/json
I1202 03:25:40.644334   16620 round_trippers.go:414]     Content-Length: 2061
I1202 03:25:40.644338   16620 round_trippers.go:414]     Date: Sun, 02 Dec 2018 03:25:40 GMT
I1202 03:25:40.644398   16620 request.go:897] Response Body: {"kind":"Table","apiVersion":"meta.k8s.io/v1beta1","metadata":{"selfLink":"/api/v1/namespaces","resourceVersion":"3970"},"columnDefinitions":[{"name":"Name","type":"string","format":"name","description":"Name must be unique within anamespace. Is required when creating resources, although some resources may allow a client to request the generation of an appropriate name automatically. Name is primarily intended for creation idempotence and configuration definition. Cannot be updated. More info: http://kubernetes.io/docs/user-guide/identifiers#names","priority":0},{"name":"Status","type":"string","format":"","description":"The status of the namespace","priority":0},{"name":"Age","type":"string","format":"","description":"CreationTimestamp is a timestamp representing the server time when this object was created. It is not guaranteed to be set in happens-before order across separate operations. Clients may not set this value. It is represented in RFC3339 form and is in UTC.\n\nPopulated by the system. Read-only. [truncated 1037 chars]
I1202 03:25:40.645111   16620 get.go:443] no kind is registered for the type v1beta1.Table
NAME          STATUS    AGE
default       Active    45m
kube-public   Active    45m
kube-system   Active    45m


使用 curl 去请求：

master $ curl -k  https://172.17.0.99:6443/api/v1/namespaces
{
  "kind": "Status",
  "apiVersion": "v1",
  "metadata": {

  },
  "status": "Failure",
  "message": "namespaces is forbidden: User \"system:anonymous\" cannot list namespaces at the cluster scope",
  "reason": "Forbidden",
  "details": {
    "kind": "namespaces"
  },
  "code": 403
}


看到这里，应该就很明显了，当前忽略掉认证过程的 curl 被判定为 system:anonymous 用户，而此用户不具备列出 namespace 的权限。

那我们是否有其他办法使用 curl 获取资源呢？ 当然有，使用 kubectl proxy 可以在本地和集群之间创建一个代理，就像这样：

master $ kubectl proxy &
[1] 22205
master $ Starting to serve on 127.0.0.1:8001

master $ curl http://127.0.0.1:8001/api/v1/namespaces
{
  "kind": "NamespaceList",
  "apiVersion": "v1",
  "metadata": {
    "selfLink": "/api/v1/namespaces",
    "resourceVersion": "5363"
  },
  "items": [
    {
      "metadata": {
        "name": "default",
        "selfLink": "/api/v1/namespaces/default",
        "uid": "a5124131-f5db-11e8-9237-0242ac110063",
        "resourceVersion": "4",
        "creationTimestamp": "2018-12-02T02:40:35Z"
      },
      "spec": {
        "finalizers": [
          "kubernetes"
        ]
      },
      "status": {
        "phase": "Active"
      }
    },
    {
      "metadata": {
        "name": "kube-public",
        "selfLink": "/api/v1/namespaces/kube-public",
        "uid": "a5153f73-f5db-11e8-9237-0242ac110063",
        "resourceVersion": "10",
        "creationTimestamp": "2018-12-02T02:40:35Z"
      },
      "spec": {
        "finalizers": [
          "kubernetes"
        ]
      },
      "status": {
        "phase": "Active"
      }
    },
    {
      "metadata": {
        "name": "kube-system",
        "selfLink": "/api/v1/namespaces/kube-system",
        "uid": "a514ad25-f5db-11e8-9237-0242ac110063",
        "resourceVersion": "9",
        "creationTimestamp": "2018-12-02T02:40:35Z"
      },
      "spec": {
        "finalizers": [
          "kubernetes"
        ]
      },
      "status": {
        "phase": "Active"
      }
    }
  ]
}


可以看到已经能正确的获取资源了，这是因为 kubectl proxy 使用了 $HOME/.kube/config 中的配置。

在 staging/src/k8s.io/client-go/tools/clientcmd/loader.go 中，有一个名为 LoadFromFile 的函数用来提供加载配置文件的功能。

func LoadFromFile(filename string) (*clientcmdapi.Config, error) {
	kubeconfigBytes, err := ioutil.ReadFile(filename)
	if err != nil {
		return nil, err
	}
	config, err := Load(kubeconfigBytes)
	if err != nil {
		return nil, err
	}
	glog.V(6).Infoln("Config loaded from file", filename)

	// set LocationOfOrigin on every Cluster, User, and Context
	for key, obj := range config.AuthInfos {
		obj.LocationOfOrigin = filename
		config.AuthInfos[key] = obj
	}
	for key, obj := range config.Clusters {
		obj.LocationOfOrigin = filename
		config.Clusters[key] = obj
	}
	for key, obj := range config.Contexts {
		obj.LocationOfOrigin = filename
		config.Contexts[key] = obj
	}

	if config.AuthInfos == nil {
		config.AuthInfos = map[string]*clientcmdapi.AuthInfo{}
	}
	if config.Clusters == nil {
		config.Clusters = map[string]*clientcmdapi.Cluster{}
	}
	if config.Contexts == nil {
		config.Contexts = map[string]*clientcmdapi.Context{}
	}

	return config, nil
}


逻辑其实很简单，读取指定的文件（一般在调用此函数前，都会先去检查是否有 KUBECONFIG 的环境变量或 --kubeconfig，如果没有才会使用默认的 $HOME/.kube/config 作为文件名）。

从以上的例子中，使用当前配置的用户可以获取资源，而 system:anonymous 不可以。可以得出 kube-apiserver 又一个重要的功能：授权。

授权（Authorization）

在第 8 节中，我们也已经讲过，K8S 支持多种授权机制，现在多数都在使用 RBAC ，我们之前使用 kubeadm 创建集群时，默认会开启 RBAC。如何创建权限可控的用户在第 8 节也已经说过。所以本节中不过多赘述了，直接看授权后的处理逻辑。

准入控制（Admission Control）

在请求进来时，会先经过认证、授权接下来会进入准入控制环节。准入控制和前两项内容不同，它不只是关注用户和行为，它还会处理请求的内容。不过它对读操作无效。

准入控制与我们前面说提到的认证、授权插件类似，支持同时开启多个。在 v1.11.3 中，默认开启的准入控制插件有：

NamespaceLifecycle,LimitRanger,ServiceAccount,PersistentVolumeClaimResize,DefaultStorageClass,DefaultTolerationSeconds,MutatingAdmissionWebhook,ValidatingAdmissionWebhook,ResourceQuota,Priority


相关的代码可查看 pkg/kubeapiserver/options/plugins.go

func DefaultOffAdmissionPlugins() sets.String {
	defaultOnPlugins := sets.NewString(
		lifecycle.PluginName,                //NamespaceLifecycle
		limitranger.PluginName,              //LimitRanger
		serviceaccount.PluginName,           //ServiceAccount
		setdefault.PluginName,               //DefaultStorageClass
		resize.PluginName,                   //PersistentVolumeClaimResize
		defaulttolerationseconds.PluginName, //DefaultTolerationSeconds
		mutatingwebhook.PluginName,          //MutatingAdmissionWebhook
		validatingwebhook.PluginName,        //ValidatingAdmissionWebhook
		resourcequota.PluginName,            //ResourceQuota
	)

	if utilfeature.DefaultFeatureGate.Enabled(features.PodPriority) {
		defaultOnPlugins.Insert(podpriority.PluginName) //PodPriority
	}

	return sets.NewString(AllOrderedPlugins...).Difference(defaultOnPlugins)
}


在这里写了一些默认开启的配置。事实上，在早之前，PersistentVolumeClaimResize 默认是不开启的，并且开启了 PersistentVolumeLabel，对于移除 Persistentvolumelabel 感兴趣的朋友可以参考下 Remove the PersistentVolumeLabel Admission Controller 。

这里对几个比较常见的插件做下说明：


NamespaceLifecycle：它可以保证正在终止的 Namespace 不允许创建对象，不允许请求不存在的 Namespace 以及保证默认的 default, kube-system 之类的命名空间不被删除。核心的代码是：


  if a.GetOperation() == admission.Delete && a.GetKind().GroupKind() == v1.SchemeGroupVersion.WithKind("Namespace").GroupKind() && l.immortalNamespaces.Has(a.GetName()) {
  	return errors.NewForbidden(a.GetResource().GroupResource(), a.GetName(), fmt.Errorf("this namespace may not be deleted"))
  }


如果删除默认的 Namespace 则会得到下面的异常：

  master $ kubectl delete ns kube-system
  Error from server (Forbidden): namespaces "kube-system" is forbidden: this namespace may not be deleted
  master $ kubectl delete ns kube-public
  Error from server (Forbidden): namespaces "kube-public" is forbidden: this namespace may not be deleted
  master $ kubectl delete ns default
  Error from server (Forbidden): namespaces "default" is forbidden: this namespace may not be deleted



LimitRanger：为 Pod 设置默认请求资源的限制。

ServiceAccount：可按照预设规则创建 Serviceaccount 。比如都有统一的前缀：system:serviceaccount:。

DefaultStorageClass：为 PVC 设置默认 StorageClass。

DefaultTolerationSeconds：设置 Pod 的默认 forgiveness toleration 为 5 分钟。这个可能常会看到。

MutatingAdmissionWebhook 和 ValidatingAdmissionWebhook：这两个都是通过 Webhook 验证或者修改请求，唯一的区别是一个是顺序进行，一个是并行进行的。

ResourceQuota：限制 Pod 请求配额。

AlwaysPullImages：总是拉取镜像。

AlwaysAdmit：总是接受所有请求。


处理请求

前面已经说到，一个请求依次会经过认证，授权，准入控制等环节，当这些环节都已经通过后，该请求便到了 kube-apiserver 的实际处理逻辑中了。

其实和普通的 Web server 类似，kube-apiserver 提供了 restful 的接口，增删改查等基本功能都基本类似。这里先暂时不再深入。

总结

通过本节，我们学习到了 kube-apiserver 的基本工作逻辑，各类 API 请求先后通过认证，授权，准入控制等一系列环节后，进入到 kube-apiserver 的 Registry 进行相关逻辑处理。

至于需要进行持久化或者需要与后端存储交互的部分，我们在下节会介绍 etcd 到时再看 K8S 是如何将后端存储抽象化，从 etcd v2 升级至 v3 的。

kube-apiserver 包含的东西有很多，当你在终端下执行 ./kube-apiserver -h 时，会发现有大量的参数。

这些参数除了认证，授权，准入控制相关功能外，还有审计，证书，存储等配置。主体功能、原理了解后，这些参数也就会比较容易配置了。

                        
                        
                            