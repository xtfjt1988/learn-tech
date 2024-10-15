
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            21 扩展增强：CoreDNS
                            整体概览

通过前面的学习，我们知道在 K8S 中有一套默认的集群内 DNS 服务，我们通常把它叫做 kube-dns，它基于 SkyDNS，为我们在服务注册发现方面提供了很大的便利。

比如，在我们的示例项目 SayThx 中，各组件便是依赖 DNS 进行彼此间的调用。

本节，我们将介绍的 CoreDNS 是 CNCF 旗下又一孵化项目，在 K8S 1.9 版本中加入并进入 Alpha 阶段。我们当前是以 K8S 1.11 的版本进行介绍，它并不是默认的 DNS 服务，但是它作为 K8S 的 DNS 插件的功能已经 GA 。

CoreDNS 在 K8S 1.13 版本中才正式成为默认的 DNS 服务。

CoreDNS 是什么

首先，我们需要明确 CoreDNS 是一个独立项目，它不仅可支持在 K8S 中使用，你也可以在你任何需要 DNS 服务的时候使用它。

CoreDNS 使用 Go 语言实现，部署非常方便。

它的扩展性很强，很多功能特性都是通过插件完成的，它不仅有大量的内置插件，同时也有很丰富的第三方插件。甚至你自己写一个插件也非常的容易。

如何安装使用 CoreDNS

我们这里主要是为了说明如何在 K8S 环境中使用它，所以对于独立安装部署它不做说明。

本小册中我们使用的是 K8S 1.11 版本，在第 5 小节 《搭建 Kubernetes 集群》中，我们介绍了使用 kubeadm 搭建集群。

使用 kubeadm 创建集群时候 kubeadm init 可以传递 --feature-gates 参数，用于启用一些额外的特性。

比如在之前版本中，我们可以通过 kubeadm init --feature-gates CoreDNS=true 在创建集群时候启用 CoreDNS。

而在 1.11 版本中，使用 kubeadm 创建集群时 CoreDNS 已经被默认启用，这也从侧面证明了 CoreDNS 在 K8S 中达到了生产可用的状态。

我们来看一下创建集群时的日志输出：

[root@master ~]# kubeadm init
[init] using Kubernetes version: v1.11.3               
[preflight] running pre-flight checks
...
[bootstraptoken] creating the "cluster-info" ConfigMap in the "kube-public" namespace
[addons] Applied essential addon: CoreDNS
[addons] Applied essential addon: kube-proxy

Your Kubernetes master has initialized successfully!


可以看到创建时已经启用了 CoreDNS 的扩展，待集群创建完成后，可用过以下方式进行查看：

master $ kubectl -n kube-system get all  -l k8s-app=kube-dns -o wide
NAME                           READY     STATUS    RESTARTS   AGE       IP          NODE      NOMINATED NODE
pod/coredns-78fcdf6894-5zbx4   1/1       Running   0          1h        10.32.0.3   node01    <none>
pod/coredns-78fcdf6894-cxdw8   1/1       Running   0          1h        10.32.0.2   node01    <none>

NAME               TYPE        CLUSTER-IP   EXTERNAL-IP   PORT(S)         AGE       SELECTOR
service/kube-dns   ClusterIP   10.96.0.10   <none>        53/UDP,53/TCP   1h        k8s-app=kube-dns

NAME                      DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE       CONTAINERS   IMAGES                     SELECTOR
deployment.apps/coredns   2         2         2            2           1h        coredns      k8s.gcr.io/coredns:1.1.3   k8s-app=kube-dns

NAME                                 DESIRED   CURRENT   READY     AGE       CONTAINERS   IMAGES                   SELECTOR
replicaset.apps/coredns-78fcdf6894   2         2         2         1h        coredns      k8s.gcr.io/coredns:1.1.3   k8s-app=kube-dns,pod-template-hash=3497892450


这里主要是为了兼容 K8S 原有的 kube-dns 所以标签和 Service 的名字都还使用了 kube-dns，但实际在运行的则是 CoreDNS。

验证 CoreDNS 功能

从上面的输出我们看到 CoreDNS 的 Pod 运行正常，现在测试下它是否能正确解析。仍然以我们的示例项目 SayThx 为例，先 clone 项目，进入到项目的 deploy 目录中。

master $ cd saythx/deploy/
master $ ls
backend-deployment.yaml  frontend-deployment.yaml  namespace.yaml         redis-service.yaml
backend-service.yaml     frontend-service.yaml     redis-deployment.yaml  work-deployment.yaml
master $ kubectl apply -f namespace.yaml
namespace/work created
master $ kubectl apply -f redis-deployment.yaml
deployment.apps/saythx-redis created
master $ kubectl apply -f redis-service.yaml
service/saythx-redis created



查看其部署情况：


master $ kubectl -n work get all
NAME                               READY     STATUS    RESTARTS   AGE
pod/saythx-redis-8558c7d7d-8v4lp   1/1       Running   0          2m

NAME                   TYPE       CLUSTER-IP       EXTERNAL-IP   PORT(S)          AGE
service/saythx-redis   NodePort   10.109.215.147   <none>        6379:31438/TCP   2m

NAME                           DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/saythx-redis   1         1         1            1           2m

NAME                                     DESIRED   CURRENT   READY     AGE
replicaset.apps/saythx-redis-8558c7d7d   1         1         1         2m



验证 DNS 是否正确解析:


# 使用 AlpineLinux 的镜像创建一个 Pod 并进入其中
master $ kubectl run alpine -it --rm --restart='Never' --image='alpine' sh
If you don't see a command prompt, try pressing enter.
/ # apk add --no-cache bind-tools
fetch http://dl-cdn.alpinelinux.org/alpine/v3.8/main/x86_64/APKINDEX.tar.gz
fetch http://dl-cdn.alpinelinux.org/alpine/v3.8/community/x86_64/APKINDEX.tar.gz
(1/5) Installing libgcc (6.4.0-r9)
(2/5) Installing json-c (0.13.1-r0)
(3/5) Installing libxml2 (2.9.8-r1)
(4/5) Installing bind-libs (9.12.3-r0)
(5/5) Installing bind-tools (9.12.3-r0)
Executing busybox-1.28.4-r2.trigger
OK: 9 MiB in 18 packages

# 安装完 dig 命令所在包之后，使用 dig 命令进行验证
/ # dig @10.32.0.2 saythx-redis.work.svc.cluster.local +noall +answer

; <<>> DiG 9.12.3 <<>> @10.32.0.2 saythx-redis.work.svc.cluster.local +noall +answer
; (1 server found)
;; global options: +cmd
saythx-redis.work.svc.cluster.local. 5 IN A     10.109.215.147


通过以上操作，可以看到相应的 Service 记录可被正确解析。这里有几个点需要注意：


域名解析是可跨 Namespace 的


刚才的示例中，我们没有指定 Namespace 所以刚才我们所在的 Namespace 是 default。而我们的解析实验成功了。说明 CoreDNS 的解析是全局的。虽然解析是全局的，但不代表网络互通


域名有特定格式


可以看到刚才我们使用的完整域名是 saythx-redis.work.svc.cluster.local , 注意开头的便是 Service 名.Namespace 名 当然，我们也可以直接通过 host 命令查询:

  / # host -t srv  saythx-redis.work
  saythx-redis.work.svc.cluster.local has SRV record 0 100 6379 saythx-redis.work.svc.cluster.local.


配置和监控

CoreDNS 使用 ConfigMap 的方式进行配置，但是如果更改了配置，Pod 重启后才会生效。

我们通过以下命令可查看其配置：

master $ kubectl -n kube-system get configmap coredns -o yaml
apiVersion: v1
data:
  Corefile: |
    .:53 {
        errors
        health
        kubernetes cluster.local in-addr.arpa ip6.arpa {
           pods insecure
           upstream
           fallthrough in-addr.arpa ip6.arpa
        }
        prometheus :9153
        proxy . /etc/resolv.conf
        cache 30
        reload
    }
kind: ConfigMap
metadata:
  creationTimestamp: 2018-12-22T16:45:47Z
  name: coredns
  namespace: kube-system
  resourceVersion: "217"
  selfLink: /api/v1/namespaces/kube-system/configmaps/coredns
  uid: 0882e51b-0609-11e9-b25e-0242ac110057


Corefile 便是它的配置文件，可以看到它启动了类似 kubernetes, prometheus 等插件。

注意 kubernetes 插件的配置，使用的域是 cluster.local ，这也是上面我们提到域名格式时候后半部分未解释的部分。

至于 prometheus 插件，则是监听在 9153 端口上提供了符合 Prometheus 标准的 metrics 接口，可用于监控等。关于监控的部分，可参考第 23 节。

总结

在本节中，我们介绍了 CoreDNS 的基本情况，它是以 Go 编写的灵活可扩展的 DNS 服务器。

使用 CoreDNS 代替 kube-dns 主要是为了解决一些 kube-dns 时期的问题，比如说原先 kube-dns 的时候，一个 Pod 中还需要包含 kube-dns, sidecar 和 dnsmasq 的容器，而每当 dnsmasq 出现漏洞时，就不得不让 K8S 发个安全补丁才能进行更新。

CoreDNS 有丰富的插件，可以满足更多样的应用需求，同时 kubernetes 插件还包含了一些独特的功能，比如 Pod 验证之类的，可增加安全性。

同时 CoreDNS 在 1.13 版本中会作为默认的 DNS 服务器使用，所以应该给它更多的关注。

在下节，我们将介绍 Ingress，看看如果使用不同于之前使用的 NodePort 的方式将服务暴露于外部。

                        
                        
                            