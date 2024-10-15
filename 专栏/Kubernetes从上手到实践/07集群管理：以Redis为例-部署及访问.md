
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            07 集群管理：以 Redis 为例-部署及访问
                            上节我们已经学习了 kubectl 的基础使用，本节我们使用 kubectl 在 K8S 中进行部署。

前面我们已经说过，Pod 是 K8S 中最小的调度单元，所以我们无法直接在 K8S 中运行一个 container 但是我们可以运行一个 Pod 而这个 Pod 中只包含一个 container 。

从 kubectl run 开始

kubectl run 的基础用法如下：

Usage:
  kubectl run NAME --image=image [--env="key=value"] [--port=port] [--replicas=replicas] [--dry-run=bool] [--overrides=inline-json] [--command] -- [COMMAND] [args...] [options]


NAME 和 --image 是必需项。分别代表此次部署的名字及所使用的镜像，其余部分之后进行解释。当然，在我们实际使用时，推荐编写配置文件并通过 kubectl create 进行部署。

使用最小的 Redis 镜像

在 Redis 的官方镜像列表可以看到有很多的 tag 可供选择，其中使用 Alpine Linux 作为基础的镜像体积最小，下载较为方便。我们选择 redis:alpine 这个镜像进行部署。

部署

现在我们只部署一个 Redis 实例。

➜  ~ kubectl run redis --image='redis:alpine'
deployment.apps/redis created


可以看到提示 deployment.apps/redis created 这个稍后进行解释，我们使用 kubectl get all 来看看到底发生了什么。

➜  ~ kubectl get all
NAME                         READY     STATUS    RESTARTS   AGE
pod/redis-7c7545cbcb-2m6rp   1/1       Running   0          30s

NAME                 TYPE        CLUSTER-IP   EXTERNAL-IP   PORT(S)   AGE
service/kubernetes   ClusterIP   10.96.0.1    <none>        443/TCP   32s

NAME                    DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/redis   1         1         1            1           30s

NAME                               DESIRED   CURRENT   READY     AGE
replicaset.apps/redis-7c7545cbcb   1         1         1         30s


可以看到其中有我们刚才执行 run 操作后创建的 deployment.apps/redis，还有 replicaset.apps/redis-7c7545cbcb, service/kubernetes 以及 pod/redis-7c7545cbcb-f984p。

使用 kubectl get all 输出内容的格式 / 前代表类型，/ 后是名称。

这些分别代表什么含义？

Deployment

Deployment 是一种高级别的抽象，允许我们进行扩容，滚动更新及降级等操作。我们使用 kubectl run redis --image='redis:alpine 命令便创建了一个名为 redis 的 Deployment，并指定了其使用的镜像为 redis:alpine。

同时 K8S 会默认为其增加一些标签（Label）。我们可以通过更改 get 的输出格式进行查看。

➜  ~ kubectl get deployment.apps/redis -o wide 
NAME      DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE       CONTAINERS   IMAGES         SELECTOR
redis     1         1         1            1           40s       redis        redis:alpine   run=redis
➜  ~ kubectl get deploy redis -o wide          
NAME      DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE       CONTAINERS   IMAGES         SELECTOR
redis     1         1         1            1           40s       redis        redis:alpine   run=redis


那么这些 Label 有什么作用呢？它们可作为选择条件进行使用。如：

➜  ~ kubectl get deploy -l run=redis -o wide 
NAME      DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE       CONTAINERS   IMAGES         SELECTOR
redis     1         1         1            1           11h       redis        redis:alpine   run=redis
➜  ~ kubectl get deploy -l run=test -o wide  # 由于我们并没有创建过 test 所以查不到任何东西
No resources found.


我们在应用部署或更新时总是会考虑的一个问题是如何平滑升级，利用 Deployment 也能很方便的进行金丝雀发布（Canary deployments）。这主要也依赖 Label 和 Selector， 后面我们再详细介绍如何实现。

Deployment 的创建除了使用我们这里提到的方式外，更推荐的方式便是使用 yaml 格式的配置文件。在配置文件中主要是声明一种预期的状态，而其他组件则负责协同调度并最终达成这种预期的状态。当然这也是它的关键作用之一，将 Pod 托管给下面将要介绍的 ReplicaSet。

ReplicaSet

ReplicaSet 是一种较低级别的结构，允许进行扩容。

我们上面已经提到 Deployment 主要是声明一种预期的状态，并且会将 Pod 托管给 ReplicaSet，而 ReplicaSet 则会去检查当前的 Pod 数量及状态是否符合预期，并尽量满足这一预期。

ReplicaSet 可以由我们自行创建，但一般情况下不推荐这样去做，因为如果这样做了，那其实就相当于跳过了 Deployment 的部分，Deployment 所带来的功能或者特性我们便都使用不到了。

除了 ReplicaSet 外，我们还有一个选择名为 ReplicationController，这两者的主要区别更多的在选择器上，我们后面再做讨论。现在推荐的做法是 ReplicaSet 所以不做太多解释。

ReplicaSet 可简写为 rs，通过以下命令查看：

➜  ~ kubectl get rs -o wide
NAME               DESIRED   CURRENT   READY     AGE       CONTAINERS   IMAGES         SELECTOR                           
redis-7c7545cbcb   1         1         1         11h       redis        redis:alpine   pod-template-hash=3731017676,run=redis


在输出结果中，我们注意到这里除了我们前面看到的 run=redis 标签外，还多了一个 pod-template-hash=3731017676 标签，这个标签是由 Deployment controller 自动添加的，目的是为了防止出现重复，所以将 pod-template 进行 hash 用作唯一性标识。

Service

Service 简单点说就是为了能有个稳定的入口访问我们的应用服务或者是一组 Pod。通过 Service 可以很方便的实现服务发现和负载均衡。

➜  ~ kubectl get service -o wide
NAME         TYPE        CLUSTER-IP   EXTERNAL-IP   PORT(S)   AGE       SELECTOR
kubernetes   ClusterIP   10.96.0.1    <none>        443/TCP   16m        <none>


通过使用 kubectl 查看，能看到主要会显示 Service 的名称，类型，IP，端口及创建时间和选择器等。我们来具体拆解下。

类型

Service 目前有 4 种类型：


ClusterIP： 是 K8S 当前默认的 Service 类型。将 service 暴露于一个仅集群内可访问的虚拟 IP 上。
NodePort： 是通过在集群内所有 Node 上都绑定固定端口的方式将服务暴露出来，这样便可以通过 <NodeIP>:<NodePort> 访问服务了。
LoadBalancer： 是通过 Cloud Provider 创建一个外部的负载均衡器，将服务暴露出来，并且会自动创建外部负载均衡器路由请求所需的 Nodeport 或 ClusterIP 。
ExternalName： 是通过将服务由 DNS CNAME 的方式转发到指定的域名上将服务暴露出来，这需要 kube-dns 1.7 或更高版本支持。


实践

上面已经说完了 Service 的基本类型，而我们也已经部署了一个 Redis ,当还无法访问到该服务，接下来我们将刚才部署的 Redis 服务暴露出来。

➜  ~ kubectl expose deploy/redis --port=6379 --protocol=TCP --target-port=6379 --name=redis-server  
service/redis-server exposed
➜  ~ kubectl get svc -o wide                                                                       
NAME           TYPE        CLUSTER-IP      EXTERNAL-IP   PORT(S)    AGE       SELECTOR
kubernetes     ClusterIP   10.96.0.1       <none>        443/TCP    49m       <none>
redis-server   ClusterIP   10.108.105.63   <none>        6379/TCP   4s        run=redis


通过 kubectl expose 命令将 redis server 暴露出来，这里需要进行下说明：


port： 是 Service 暴露出来的端口，可通过此端口访问 Service。
protocol： 是所用协议。当前 K8S 支持 TCP/UDP 协议，在 1.12 版本中实验性的加入了对 SCTP 协议的支持。默认是 TCP 协议。
target-port： 是实际服务所在的目标端口，请求由 port 进入通过上述指定 protocol 最终流向这里配置的端口。
name： Service 的名字，它的用处主要在 dns 方面。
type： 是前面提到的类型，如果没指定默认是 ClusterIP。


现在我们的 redis 是使用的默认类型 ClusterIP，所以并不能直接通过外部进行访问，我们使用 port-forward 的方式让它可在集群外部访问。

➜  ~ kubectl port-forward svc/redis-server 6379:6379
Forwarding from 127.0.0.1:6379 -> 6379
Forwarding from [::1]:6379 -> 6379
Handling connection for 6379


在另一个本地终端内可通过 redis-cli 工具进行连接：

➜  ~ redis-cli -h 127.0.0.1 -p 6379
127.0.0.1:6379> ping
PONG


当然，我们也可以使用 NodePort 的方式对外暴露服务。

➜  ~ kubectl expose deploy/redis --port=6379 --protocol=TCP --target-port=6379 --name=redis-server-nodeport --type=NodePort
service/redis-server-nodeport exposed
➜  ~ kubectl get service/redis-server-nodeport -o wide 
NAME                    TYPE       CLUSTER-IP       EXTERNAL-IP   PORT(S)          AGE       SELECTOR
redis-server-nodeport   NodePort   10.109.248.204   <none>        6379:31913/TCP   11s       run=redis


我们可以通过任意 Node 上的 31913 端口便可连接我们的 redis 服务。当然，这里需要注意的是这个端口范围其实是可以通过 kube-apiserver 的 service-node-port-range 进行配置的，默认是 30000-32767。

Pod

第二节中，我们提到过 Pod 是 K8S 中的最小化部署单元。我们看下当前集群中 Pod 的状态。

➜  ~ kubectl get pods
NAME                     READY     STATUS    RESTARTS   AGE
redis-7c7545cbcb-jwcf2   1/1       Running   0          8h


我们进行一次简单的扩容操作。

➜  ~ kubectl scale deploy/redis --replicas=2
deployment.extensions/redis scaled
➜  ~ kubectl get pods
NAME                     READY     STATUS    RESTARTS   AGE
redis-7c7545cbcb-jwcf2   1/1       Running   0          8h
redis-7c7545cbcb-wzh6w   1/1       Running   0          4s


可以看到 Pod 数已经增加，并且也已经是 Running 的状态了。(当然在生产环境中 Redis 服务的扩容并不是使用这种方式进行扩容的，需要看实际的部署方式以及业务的使用姿势。)

总结

本节我们使用 Redis 作为例子，学习了集群管理相关的基础知识。学习了如何进行应用部署， Service 的基础类型以及如何通过 port-forward 或 NodePort 等方式将服务提供至集群的外部访问。

同时我们学习了应用部署中主要会涉及到的几类资源 Deployment，Replicaset，Service 和 Pod 等。对这些资源及它们之间关系的掌握，对于后续集群维护或定位问题有很大的帮助。

下节，我们开始学习在生产环境中使用 K8S 至关重要的一环，权限控制。

                        
                        
                            