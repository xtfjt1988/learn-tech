
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            19 Troubleshoot
                            整体概览

通过前面的介绍，我们已经了解到了 K8S 的基础知识，核心组件原理以及如何在 K8S 中部署服务及管理服务等。

但在生产环境中，我们所面临的环境多种多样，可能会遇到各种问题。本节将结合我们已经了解到的知识，介绍一些常见问题定位和解决的思路或方法，以便大家在生产中使用 K8S 能如鱼得水。

应用部署问题

首先我们从应用部署相关的问题来入手。这里仍然使用我们的示例项目 SayThx。

clone 该项目，进入到 deploy 目录中，先 kubectl apply -f namespace.yaml 或者 kubectl create ns work 来创建一个用于实验的 Namespace 。

使用 describe 排查问题

对 redis-deployment.yaml 稍作修改，按以下方式操作：

master $ kubectl apply -f redis-deployment.yaml
deployment.apps/saythx-redis created
master $ kubectl -n work get all
NAME                                READY     STATUS             RESTARTS   AGE
pod/saythx-redis-7574c98f5d-v66fx   0/1       ImagePullBackOff   0          9s

NAME                           DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/saythx-redis   1         1         1            0           9s

NAME                                      DESIRED   CURRENT   READY     AGE
replicaset.apps/saythx-redis-7574c98f5d   1         1         0         9s


可以看到 Pod 此刻的状态是 ImagePullBackOff，这个状态表示镜像拉取失败，kubelet 退出镜像拉取。

我们在前面的内容中介绍过 kubelet 的作用之一就是负责镜像拉取，而实际上，在镜像方面的错误主要预设了 6 种，分别是 ImagePullBackOff，ImageInspectError，ErrImagePull，ErrImageNeverPull，RegistryUnavailable，InvalidImageName。

当遇到以上所述情况时，便可定位为镜像相关异常。

我们回到上面的问题当中，定位问题所在。

master $ kubectl -n work describe pod/saythx-redis-7574c98f5d-v66fx
Name:               saythx-redis-7574c98f5d-v66fx
Namespace:          work
Priority:           0
PriorityClassName:  <none>
Node:               node01/172.17.0.132
Start Time:         Tue, 18 Dec 2018 17:27:56 +0000
Labels:             app=redis
                    pod-template-hash=3130754918
Annotations:        <none>
Status:             Pending
IP:                 10.40.0.1
Controlled By:      ReplicaSet/saythx-redis-7574c98f5d
Containers:
  redis:
    Container ID:
    Image:          redis:5xx
    Image ID:
    Port:           6379/TCP
    Host Port:      0/TCP
    State:          Waiting
      Reason:       ImagePullBackOff
    Ready:          False
    Restart Count:  0
    Environment:    <none>
    Mounts:
      /var/run/secrets/kubernetes.io/serviceaccount from default-token-787w5 (ro)
Conditions:
  Type              Status
  Initialized       True
  Ready             False
  ContainersReady   False
  PodScheduled      True
Volumes:
  default-token-787w5:
    Type:        Secret (a volume populated by a Secret)
    SecretName:  default-token-787w5
    Optional:    false
QoS Class:       BestEffort
Node-Selectors:  <none>
Tolerations:     node.kubernetes.io/not-ready:NoExecute for 300s
                 node.kubernetes.io/unreachable:NoExecute for 300s
Events:
  Type     Reason          Age                 From               Message
  ----     ------          ----                ----               -------
  Normal   Scheduled       11m                 default-scheduler  Successfully assigned work/saythx-redis-7574c98f5d-v66fx to node01
  Normal   SandboxChanged  10m                 kubelet, node01    Pod sandbox changed, it will bekilled and re-created.
  Normal   BackOff         9m (x6 over 10m)    kubelet, node01    Back-off pulling image "redis:5xx"
  Normal   Pulling         9m (x4 over 10m)    kubelet, node01    pulling image "redis:5xx"
  Warning  Failed          9m (x4 over 10m)    kubelet, node01    Failed to pull image "redis:5xx": rpc error: code = Unknown desc = Error response from daemon: manifest for redis:5xx not found
  Warning  Failed          9m (x4 over 10m)    kubelet, node01    Error: ErrImagePull
  Warning  Failed          49s (x44 over 10m)  kubelet, node01    Error: ImagePullBackOff


可以看到我们现在 pull 的镜像是 redis:5xx 而实际上并不存在此 tag 的镜像，所以导致拉取失败。

使用 events 排查问题

当然，我们还有另一种方式同样可进行问题排查：

master $ kubectl -n work get events
LAST SEEN   FIRST SEEN   COUNT     NAME                                             KIND         SUBOBJECT                TYPE      REASON              SOURCE                  MESSAGE
21m         21m          1         saythx-redis.15717d6361a741a8                    Deployment                        Normal    ScalingReplicaSet   deployment-controller   Scaled up replica set saythx-redis-7574c98f5d to 1
21m         21m          1         saythx-redis-7574c98f5d-qwxgm.15717d6363eb60ff   Pod                        Normal    Scheduled           default-scheduler       Successfully assigned work/saythx-redis-7574c98f5d-qwxgm to node01
21m         21m          1         saythx-redis-7574c98f5d.15717d636309afa8         ReplicaSet                        Normal    SuccessfulCreate    replicaset-controller   Created pod: saythx-redis-7574c98f5d-qwxgm
20m         21m          2         saythx-redis-7574c98f5d-qwxgm.15717d63fa501b3f   Pod          spec.containers{redis}   Normal    BackOff             kubelet, node01         Back-off pulling image "redis:5xx"
20m         21m          2         saythx-redis-7574c98f5d-qwxgm.15717d63fa5049a9   Pod          spec.containers{redis}   Warning   Failed              kubelet, node01         Error: ImagePullBackOff
20m         21m          3         saythx-redis-7574c98f5d-qwxgm.15717d6393a1993c   Pod          spec.containers{redis}   Normal    Pulling             kubelet, node01         pulling image "redis:5xx"
20m         21m          3         saythx-redis-7574c98f5d-qwxgm.15717d63e11efc7a   Pod          spec.containers{redis}   Warning   Failed              kubelet, node01         Error: ErrImagePull
20m         21m          3         saythx-redis-7574c98f5d-qwxgm.15717d63e11e9c25   Pod          spec.containers{redis}   Warning   Failed              kubelet, node01         Failed to pull image "redis:5xx": rpc error: code = Unknown desc = Error response from daemon: manifest for redis:5xxnot found
20m         20m          1         saythx-redis-54984ff94-2bb6g.15717d6dc03799cd    Pod          spec.containers{redis}   Normal    Killing             kubelet, node01         Killing container with id docker://redis:Need to kill Pod
19m         19m          1         saythx-redis-7574c98f5d-v66fx.15717d72356528ec   Pod                        Normal    Scheduled           default-scheduler       Successfully assigned work/saythx-redis-7574c98f5d-v66fx to node01
19m         19m          1         saythx-redis-7574c98f5d.15717d722f7f1732         ReplicaSet                        Normal    SuccessfulCreate    replicaset-controller   Created pod: saythx-redis-7574c98f5d-v66fx
19m         19m          1         saythx-redis.15717d722b49e758                    Deployment                        Normal    ScalingReplicaSet   deployment-controller   Scaled up replica set saythx-redis-7574c98f5d to 1
19m         19m          1         saythx-redis-7574c98f5d-v66fx.15717d731a09b0ad   Pod                        Normal    SandboxChanged      kubelet, node01         Pod sandbox changed, it will be killed and re-created.
18m         19m          6         saythx-redis-7574c98f5d-v66fx.15717d733ab20b3d   Pod          spec.containers{redis}   Normal    BackOff             kubelet, node01         Back-off pulling image "redis:5xx"
18m         19m          4         saythx-redis-7574c98f5d-v66fx.15717d729de13541   Pod          spec.containers{redis}   Normal    Pulling             kubelet, node01         pulling image "redis:5xx"
18m         19m          4         saythx-redis-7574c98f5d-v66fx.15717d72e6ded95d   Pod          spec.containers{redis}   Warning   Failed              kubelet, node01         Error: ErrImagePull
18m         19m          4         saythx-redis-7574c98f5d-v66fx.15717d72e6de7b1c   Pod          spec.containers{redis}   Warning   Failed              kubelet, node01         Failed to pull image "redis:5xx": rpc error: code = Unknown desc = Error response from daemon: manifest for redis:5xxnot found
4m          19m          66        saythx-redis-7574c98f5d-v66fx.15717d733ab23f2c   Pod          spec.containers{redis}   Warning   Failed              kubelet, node01         Error: ImagePullBackOff
master


我们在之前介绍时，也提到过 kubelet 或者 kube-scheduler 等组件会接受某些事件等，event 便是用于记录集群内各处发生的事件之类的。

修正错误


修正配置文件


修正配置文件，然后 kubectl apply -f redis-deployment.yaml 便可应用修正后的配置文件。这种方法比较推荐，并且可以将修改过的位置纳入到版本控制系统中，有利于后续维护。


在线修改配置


使用 kubectl -n work edit deploy/saythx-redis，会打开默认的编辑器，我们可以将使用的镜像及 tag 修正为 redis:5 保存退出，便会自动应用新的配置。这种做法比较适合比较紧急或者资源是直接通过命令行创建等情况。 非特殊情况尽量不要在线修改。 且这样修改并不利于后期维护。

通过详细内容排查错误

master $ kubectl apply -f namespace.yaml
namespace/work created
master $ kubectl apply -f redis-deployment.yaml
deployment.apps/saythx-redis created
master $ vi redis-service.yaml # 稍微做了点修改
master $ kubectl apply -f redis-service.yaml
service/saythx-redis created
master $ kubectl -n work get pods,svc
NAME                               READY     STATUS    RESTARTS   AGE
pod/saythx-redis-8558c7d7d-z8prg   1/1       Running   0          47s

NAME                   TYPE       CLUSTER-IP       EXTERNAL-IP   PORT(S)          AGE
service/saythx-redis   NodePort   10.108.202.170   <none>        6379:32355/TCP   16s


通过以上的输出，大多数情况下我们的 Service 应该是可以可以正常访问了，现在我们进行下测试：

master $ docker run --rm -it --net host redis redis-cli -p 32355
Unable to find image 'redis:latest' locally
latest: Pulling from library/redis
a5a6f2f73cd8: Pull complete
a6d0f7688756: Pull complete
53e16f6135a5: Pull complete
f52b0cc4e76a: Pull complete
e841feee049e: Pull complete
ccf45e5191d0: Pull complete
Digest: sha256:bf65ecee69c43e52d0e065d094fbdfe4df6e408d47a96e56c7a29caaf31d3c35
Status: Downloaded newer image for redis:latest
Could not connect to Redis at 127.0.0.1:32355: Connection refused
not connected>


我们先来介绍这里的测试方法。 使用 Docker 的 Redis 官方镜像， --net host 是使用宿主机网络； --rm 表示停止完后即清除； -it 分别表示获取输入及获取 TTY。

通过以上测试发现不能正常连接，故而说明 Service 还是未配置好。使用前面提到的方法也可以进行排查，不过这里提供另一种排查这类问题的思路。

master $ kubectl -n work get endpoints
NAME           ENDPOINTS        AGE
saythx-redis   10.32.0.4:6380   9m


通过之前的章节，我们已经知道 Service 工作的时候是按 Endpoints 来的，这里我们发现此处的 Endpoints 是 6380 与我们预期的 6379 并不相同。所以问题定位于端口配置有误。

前面已经说过修正方法了，不再赘述。当修正完成后，再次验证：

master $ kubectl -n work get endpoints
NAME           ENDPOINTS        AGE
saythx-redis   10.32.0.4:6379   15m


Endpoints 已经正常，验证下服务是否可用：

master $ docker run --rm -it --net host redis redis-cli -p 32355
127.0.0.1:32355> ping
PONG


验证无误。

集群问题

由于我们有多个节点，况且在集群搭建和维护过程中，也会比较常见到集群相关的问题。这里我们先举个实际例子进行分析：

master $ kubectl get nodes
NAME      STATUS     ROLES     AGE       VERSION
master    Ready      master    58m       v1.11.3
node01    NotReady   <none>    58m       v1.11.3


通过 kubectl 查看，发现有一个节点 NotReady ，这在搭建集群的过程中也有可能遇到。

master $ kubectl get  node/node01 -o yaml
apiVersion: v1
kind: Node
metadata:
  annotations:
    kubeadm.alpha.kubernetes.io/cri-socket: /var/run/dockershim.sock
    node.alpha.kubernetes.io/ttl: "0"
    volumes.kubernetes.io/controller-managed-attach-detach: "true"
  creationTimestamp: 2018-12-19T16:46:59Z
  labels:
    beta.kubernetes.io/arch: amd64
    beta.kubernetes.io/os: linux
    kubernetes.io/hostname: node01
  name: node01
  resourceVersion: "4850"
  selfLink: /api/v1/nodes/node01
  uid: b440d3d5-03ad-11e9-917e-0242ac110035
spec: {}
status:
  addresses:
  - address: 172.17.0.66
    type: InternalIP
  - address: node01
    type: Hostname
  allocatable:
    cpu: "4"
    ephemeral-storage: "89032026784"
    hugepages-1Gi: "0"
    hugepages-2Mi: "0"
    memory: 3894652Ki
    pods: "110"
  capacity:
    cpu: "4"
    ephemeral-storage: 96605932Ki
    hugepages-1Gi: "0"
    hugepages-2Mi: "0"
    memory: 3997052Ki
    pods: "110"
  conditions:
  - lastHeartbeatTime: 2018-12-19T17:42:16Z
    lastTransitionTime: 2018-12-19T17:43:00Z
    message: Kubelet stopped posting node status.
    reason: NodeStatusUnknown
    status: Unknown
    type: OutOfDisk
  - lastHeartbeatTime: 2018-12-19T17:42:16Z
    lastTransitionTime: 2018-12-19T17:43:00Z
    message: Kubelet stopped posting node status.
    reason: NodeStatusUnknown
    status: Unknown
    type: MemoryPressure
  - lastHeartbeatTime: 2018-12-19T17:42:16Z
    lastTransitionTime: 2018-12-19T17:43:00Z
    message: Kubelet stopped posting node status.
    reason: NodeStatusUnknown
    status: Unknown
    type: DiskPressure
  - lastHeartbeatTime: 2018-12-19T17:42:16Z
    lastTransitionTime: 2018-12-19T16:46:59Z
    message: kubelet has sufficient PID available
    reason: KubeletHasSufficientPID
    status: "False"
    type: PIDPressure
  - lastHeartbeatTime: 2018-12-19T17:42:16Z
    lastTransitionTime: 2018-12-19T17:43:00Z
    message: Kubelet stopped posting node status.
    reason: NodeStatusUnknown
    status: Unknown
    type: Ready
  daemonEndpoints:
    kubeletEndpoint:
      Port: 10250
  ...


我们之前介绍 kubelet 时说过， kubelet 的作用之一便是将自身注册至 kube-apiserver。

这里的 message 信息说明 kubelet 不再向 kube-apiserver 发送心跳包之类的了，所以被判定为 NotReady 的状态。

接下来，我们登录 node01 机器查看 kubelet 的状态。

node01 $ systemctl status kubelet
● kubelet.service - kubelet: The Kubernetes Node Agent
   Loaded: loaded (/lib/systemd/system/kubelet.service; enabled; vendor preset: enabled)
  Drop-In: /etc/systemd/system/kubelet.service.d
           └─kubeadm.conf
   Active: inactive (dead) since Wed 2018-12-19 17:42:17 UTC; 18min ago
     Docs: https://kubernetes.io/docs/home/
  Process: 1693 ExecStart=/usr/bin/kubelet $KUBELET_KUBECONFIG_ARGS $KUBELET_CONFIG_ARGS $KUBELET_
 Main PID: 1693 (code=exited, status=0/SUCCESS)


可以看到该机器上 kubelet 没有启动。现在将其启动，稍等片刻看看节群中 Node 的状态。

master $ kubectl get nodes
NAME      STATUS    ROLES     AGE       VERSION
master    Ready     master    1h        v1.11.3
node01    Ready     <none>    1h        v1.11.3


总结

本节我们介绍了 K8S 中常用的问题排查和解决思路，但实际生产环境中情况会有和更多不确定因素，掌握本节中介绍的基础，有利于之后生产环境中进行常规问题的排查。

当然，本节只是介绍通过 kubectl 来定位和解决问题，个别情况下我们需要登录相关的节点，实际去使用 Docker 工具等进行问题的详细排查。

至此，K8S 的基础原理和常规问题排查思路等都已经通过包括本节在内的 19 小节介绍完毕，相信你现在已经迫不及待的想要使用 K8S 了。

不过 kubectl 作为命令行工具也许有些人会不习惯使用，下节，我们将介绍 K8S 的扩展组件 kube-dashboard 了解它的主要功能及带给我们的便利。

                        
                        
                            