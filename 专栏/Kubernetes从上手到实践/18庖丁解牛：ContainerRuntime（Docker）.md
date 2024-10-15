
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            18  庖丁解牛：Container Runtime （Docker）
                            整体概览

我们在第 3 节的时候，提到过 Container Runtime 的概念，也大致介绍过它的主要作用在于下载镜像，运行容器等。

经过我们前面的学习，kube-scheduler 决定了 Pod 将被调度到哪个 Node 上，而 kubelet 则负责 Pod 在此 Node 上可按预期工作。如果没有 Container Runtime，那 Pod 中的 container 在该 Node 上也便无法正常启动运行了。

本节中，我们以当前最为通用的 Container Runtime Docker 为例进行介绍。

Container Runtime 是什么

Container Runtime 我们通常叫它容器运行时，而这一概念的产生也是由于容器化技术和 K8S 的大力发展，为了统一工业标准，也为了避免 K8S 绑定于特定的容器运行时，所以便成立了 Open Container Initiative (OCI) 组织，致力于将容器运行时标准化和容器镜像标准化。

凡是遵守此标准的实现，均可由标准格式的镜像启动相应的容器，并完成一些特定的操作。

Docker 是什么

Docker 是一个容器管理平台，它最初是被设计用于快速创建，发布和运行容器的工具，不过随着它的发展，其中集成了越来越多的功能。

Docker 也可以说是一个包含标准容器运行时的工具集，当前版本中默认的 runtime 称之为 runc。 关于 runc 相关的一些内容可参考我之前的一篇文章。

当然，这里提到了 默认的运行时 那也就意味着它可支持其他的运行时实现。

CRI 是什么

说到这里，我们就会发现，K8S 作为目前云原生技术体系中最重要的一环，为了让它更有扩展性，当然也不会将自己完全局限于某一种特定的容器运行时。

自 K8S 1.5 （2016 年 11 月）开始，新增了一个容器运行时的插件 API，并称之为 CRI （Container Runtime Interface），通过 CRI 可以支持 kubelet 使用不同的容器运行时，而不需要重新编译。

CRI 主要是基于 gRPC 实现了 RuntimeService 和 ImageService 这两个服务，可以参考 pkg/kubelet/apis/cri/runtime/v1alpha2/api.proto 中的 API 定义。由于本节侧重于 Container Runtime/Docker 这里就不对 CRI 的具体实现进行展开了。

只要继续将 kubelet 当作 agent 的角色，而它与基于 CRI 实现的 CRI shim 服务进行通信理解即可。

Docker 如何工作

这里我们主要介绍在 K8S 中一些 Docker 常见的动作。

部署一个 Redis

master $ kubectl run redis --image=redis
deployment.apps/redis created
master $ kubectl get all
NAME                        READY     STATUS              RESTARTS   AGE
pod/redis-bb7894d65-7vsj8   0/1       ContainerCreating   0          6s

NAME                 TYPE        CLUSTER-IP   EXTERNAL-IP   PORT(S)   AGE
service/kubernetes   ClusterIP   10.96.0.1    <none>        443/TCP   26m

NAME                    DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/redis   1         1         1            0           6s

NAME                              DESIRED   CURRENT   READY     AGE
replicaset.apps/redis-bb7894d65   1         1         0         6s


我们直接使用 kubectl run 的方式部署了一个 Redis

查看详情

master $ kubectl describe pod/redis-bb7894d65-7vsj8
Name:               redis-bb7894d65-7vsj8
Namespace:          default
Priority:           0
PriorityClassName:  <none>
Node:               node01/172.17.0.21
Start Time:         Sat, 15 Dec 2018 04:48:49 +0000
Labels:             pod-template-hash=663450821
                    run=redis
Annotations:        <none>
Status:             Running
IP:                 10.40.0.1
Controlled By:      ReplicaSet/redis-bb7894d65
Containers:
  redis:
    Container ID:   docker://ab87085456aca76825dd639bcde27160d9c2c84cac5388585bcc9ed3afda6522
    Image:          redis
    Image ID:       docker-pullable://redis@sha256:010a8bd5c6a9d469441aa35187d18c181e3195368bce309348b3ee639fce96e0
    Port:           <none>
    Host Port:      <none>
    State:          Running
      Started:      Sat, 15 Dec 2018 04:48:57 +0000
    Ready:          True
    Restart Count:  0
    Environment:    <none>
    Mounts:
      /var/run/secrets/kubernetes.io/serviceaccount from default-token-zxt27 (ro)
Conditions:
  Type              Status
  Initialized       True
  Ready             True
  ContainersReady   True
  PodScheduled      True
Volumes:
  default-token-zxt27:
    Type:        Secret (a volume populated by a Secret)
    SecretName:  default-token-zxt27
    Optional:    false
QoS Class:       BestEffort
Node-Selectors:  <none>
Tolerations:     node.kubernetes.io/not-ready:NoExecute for 300s
                 node.kubernetes.io/unreachable:NoExecute for 300s
Events:
  Type    Reason     Age   From               Message
  ----    ------     ----  ----               -------
  Normal  Scheduled  7m    default-scheduler  Successfully assigned default/redis-bb7894d65-7vsj8to node01
  Normal  Pulling    7m    kubelet, node01    pulling image "redis"
  Normal  Pulled     7m    kubelet, node01    Successfully pulled image "redis"
  Normal  Created    7m    kubelet, node01    Created container
  Normal  Started    7m    kubelet, node01    Started container


可以通过 kubectl describe 查看该 Pod 的事件详情。这里主要有几个阶段。

调度

Normal  Scheduled  7m    default-scheduler  Successfully assigned default/redis-bb7894d65-7vsj8to node01


在第 15 小节 kube-scheduler 中我们介绍过，通过 kube-scheduler 可以决定 Pod 会调度到哪个 Node。本例中，redis-bb7894d65-7vsj8to 被调度到了 node01。

pull 镜像

Normal  Pulling    7m    kubelet, node01    pulling image "redis"
Normal  Pulled     7m    kubelet, node01    Successfully pulled image "redis"


这里 kubelet 及该节点上的 Container Runtime （Docker）开始发挥作用，先拉取镜像。如果此刻你登录 node01 的机器，执行 docker pull redis 便可同步看到拉取进度。

创建镜像并启动

Normal  Created    7m    kubelet, node01    Created container
Normal  Started    7m    kubelet, node01    Started container


拉取镜像完成后，便会开始创建并启动该容器，并返回任务结果。此刻登录 node01 机器，便会看到当前在运行的容器了。

node01 $ docker ps |grep redis
ab87085456ac        redis@sha256:010a8bd5c6a9d469441aa35187d18c181e3195368bce309348b3ee639fce96e0  "docker-entrypoint..."   19 minutes ago      Up 19 minutes                           k8s_redis_redis-bb7894d65-7vsj8_default_b693b56c-0024-11e9-9bab-0242ac11000a_0
8f264abd82fe        k8s.gcr.io/pause:3.1  "/pause"                 19 minutes ago      Up 19 minutes                           k8s_POD_redis-bb7894d65-7vsj8_default_b693b56c-0024-11e9-9bab-0242ac11000a_0


总结

本节我们介绍了 Container Runtime 的基本概念，及 K8S 为了能增加扩展性，提供了统一的 CRI 插件接口，可用于支持多种容器运行时。

当前使用最为广泛的是 Docker，当前还支持的主要有 runc，Containerd，runV 以及 rkt 等。

由于 Docker 的知识点很多，关于 Docker 的实践和内部原理可参考我之前的一次分享 Docker 实战和基本原理。

在使用 K8S 时，也有极个别情况需要通过排查 Docker 的日志来分析问题。

至此，K8S 中主要的核心组件我们已经介绍完毕，下节我们主要集中于在 K8S 环境中，如何定位和解决问题，以及类似刚才提到的需要通过 Docker 进行排查问题的情况。

                        
                        
                            