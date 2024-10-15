
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            16 庖丁解牛：kubelet
                            整体概览

+--------------------------------------------------------+       
| +---------------------+        +---------------------+ |       
| |      kubelet        |        |     kube-proxy      | |       
| |                     |        |                     | |       
| +---------------------+        +---------------------+ |       
| +----------------------------------------------------+ |       
| | Container Runtime (Docker)                         | |       
| | +---------------------+    +---------------------+ | |       
| | |Pod                  |    |Pod                  | | |       
| | | +-----+ +-----+     |    |+-----++-----++-----+| | |       
| | | |C1   | |C2   |     |    ||C1   ||C2   ||C3   || | |       
| | | |     | |     |     |    ||     ||     ||     || | |       
| | | +-----+ +-----+     |    |+-----++-----++-----+| | |       
| | +---------------------+    +---------------------+ | |       
| +----------------------------------------------------+ |       
+--------------------------------------------------------+  


在第 3 节《宏观认识：整体架构》 中，我们知道了 K8S 中 Node 由一些必要的组件构成，而其中最为核心的当属 kubelet 了，如果没有 kubelet 的存在，那我们预期的各类资源就只能存在于 Master 的相关组件中了，而 K8S 也很能只是一个 CRUD 的普通程序了。本节，我们来介绍下 kubelet 及它是如何完成这一系列任务的。

kubelet 是什么

按照一般架构设计上的习惯，kubelet 所承担的角色一般会被叫做 agent，这里叫做 kubelet 很大程度上受 Borg 的命名影响，Borg 里面也有一个 Borglet 的组件存在。kubelet 便是 K8S 中的 agent，负责 Node 和 Pod 相关的管理任务。

同样的，在我们下载 K8S 二进制文件解压后，便可以得到 kubelet 的可执行文件。在第 5 节中，我们也完成了 kubelet 以 systemd 进行启动和管理的相关配置。

kubelet 有什么作用

通常来讲 agent 这样的角色起到的作用首先便是要能够注册，让 server 端知道它的存在，所以这便是它的第一个作用：节点管理。

节点管理

当我们执行 kubelet --help 的时候，会看到它所支持的可配置参数，其中有一个 --register-node 参数便是用于控制是否向 kube-apiserver 注册节点的，默认是开启的。

我们在第 5 节中还介绍了如何新增一个 Node，当 kubeadm join 执行成功后，你便可以通过 kubectl get node 查看到新加入集群中的 Node，与此同时，你也可以在该节点上通过以下命令查看 kubelet 的状态。

master $ systemctl status kubelet
● kubelet.service - kubelet: The Kubernetes Agent
   Loaded: loaded (/etc/systemd/system/kubelet.service; enabled; vendor preset: disabled)
  Drop-In: /etc/systemd/system/kubelet.service.d
           └─kubeadm.conf
   Active: active (running) since Thu 2018-12-13 07:49:51 UTC; 32min ago
     Docs: http://kubernetes.io/docs/
 Main PID: 3876259 (kubelet)
   Memory: 66.3M
   CGroup: /system.slice/kubelet.service
           └─3876259 /usr/bin/kubelet --bootstrap-kubeconfig=/etc/kubernetes/bootstrap-kubelet.conf --kubeconfig=/etc/kubernete...


当我们查看 Node 信息时，也能得到如下输出：

master $ kubectl get nodes node01 -o yaml
apiVersion: v1
kind: Node
metadata:
  annotations:
    kubeadm.alpha.kubernetes.io/cri-socket: /var/run/dockershim.sock
    node.alpha.kubernetes.io/ttl: "0"
    volumes.kubernetes.io/controller-managed-attach-detach: "true"
  creationTimestamp: 2018-12-13T07:50:47Z
  labels:
    beta.kubernetes.io/arch: amd64
    beta.kubernetes.io/os: linux
    kubernetes.io/hostname: node01
  name: node01
  resourceVersion: "4242"
  selfLink: /api/v1/nodes/node01
  uid: cd612df6-feab-11e8-9a0b-0242ac110096
spec: {}
status:
  addresses:
  - address: 172.17.0.152
    type: InternalIP
  - address: node01
    type: Hostname
  allocatable:
    cpu: "4"
    ephemeral-storage: "89032026784"
    hugepages-1Gi: "0"
    hugepages-2Mi: "0"
    memory: 3894788Ki
    pods: "110"
  capacity:
    cpu: "4"
    ephemeral-storage: 96605932Ki
    hugepages-1Gi: "0"
    hugepages-2Mi: "0"
    memory: 3997188Ki
    pods: "110"
  conditions:
  - lastHeartbeatTime: 2018-12-13T08:39:41Z
    lastTransitionTime: 2018-12-13T07:50:47Z
    message: kubelet has sufficient disk space available
    reason: KubeletHasSufficientDisk
    status: "False"
    type: OutOfDisk
  - lastHeartbeatTime: 2018-12-13T08:39:41Z
    lastTransitionTime: 2018-12-13T07:50:47Z
    message: kubelet has sufficient memory available
    reason: KubeletHasSufficientMemory
    status: "False"
    type: MemoryPressure
  - lastHeartbeatTime: 2018-12-13T08:39:41Z
    lastTransitionTime: 2018-12-13T07:50:47Z
    message: kubelet has no disk pressure
    reason: KubeletHasNoDiskPressure
    status: "False"
    type: DiskPressure
  - lastHeartbeatTime: 2018-12-13T08:39:41Z
    lastTransitionTime: 2018-12-13T07:50:47Z
    message: kubelet has sufficient PID available
    reason: KubeletHasSufficientPID
    status: "False"
    type: PIDPressure
  - lastHeartbeatTime: 2018-12-13T08:39:41Z
    lastTransitionTime: 2018-12-13T07:51:37Z
    message: kubelet is posting ready status. AppArmor enabled
    reason: KubeletReady
    status: "True"
    type: Ready
  daemonEndpoints:
    kubeletEndpoint:
      Port: 10250
  images:
  - names:
    - k8s.gcr.io/kube-apiserver-amd64@sha256:956bea8c139620c9fc823fb81ff9b5647582b53bd33904302987d56ab24fc187
    - k8s.gcr.io/kube-apiserver-amd64:v1.11.3
    sizeBytes: 186676561
  nodeInfo:
    architecture: amd64
    bootID: 89ced22c-f7f8-4c4d-ad0d-d10887ab900e
    containerRuntimeVersion: docker://18.6.0
    kernelVersion: 4.4.0-62-generic
    kubeProxyVersion: v1.11.3
    kubeletVersion: v1.11.3
    machineID: 26ba042302eea8095d6576975c120eeb
    operatingSystem: linux
    osImage: Ubuntu 16.04.2 LTS
    systemUUID: 26ba042302eea8095d6576975c120eeb


可以看到 kubelet 不仅将自己注册给了 kube-apiserver，同时它所在机器的信息也都进行了上报，包括 CPU，内存，IP 信息等。

这其中有我们在第 2 节中提到的关于 Node 状态相关的一些信息，可以对照着看看。

当然这里除了这些信息外，还有些值得注意的，比如 daemonEndpoints 之类的，可以看到目前 kubelet 监听在了 10250 端口，这个端口可通过 --port 配置，但是之后会被废弃掉，我们是写入了 /var/lib/kubelet/config.yaml 的配置文件中。

master $ cat /var/lib/kubelet/config.yaml
address: 0.0.0.0
apiVersion: kubelet.config.k8s.io/v1beta1
authentication:
  anonymous:
    enabled: false
  webhook:
    cacheTTL: 2m0s
    enabled: true
  x509:
    clientCAFile: /etc/kubernetes/pki/ca.crt
authorization:
  mode: Webhook
  webhook:
    cacheAuthorizedTTL: 5m0s
    cacheUnauthorizedTTL: 30s
cgroupDriver: cgroupfs
cgroupsPerQOS: true
clusterDNS:
- 10.96.0.10
clusterDomain: cluster.local
containerLogMaxFiles: 5
containerLogMaxSize: 10Mi
contentType: application/vnd.kubernetes.protobuf
cpuCFSQuota: true
cpuManagerPolicy: none
cpuManagerReconcilePeriod: 10s
enableControllerAttachDetach: true
enableDebuggingHandlers: true
enforceNodeAllocatable:
- pods
eventBurst: 10
eventRecordQPS: 5
evictionHard:
  imagefs.available: 15%
  memory.available: 100Mi
  nodefs.available: 10%
  nodefs.inodesFree: 5%
evictionPressureTransitionPeriod: 5m0s
failSwapOn: true
fileCheckFrequency: 20s
hairpinMode: promiscuous-bridge
healthzBindAddress: 127.0.0.1
healthzPort: 10248
httpCheckFrequency: 20s
imageGCHighThresholdPercent: 85
imageGCLowThresholdPercent: 80
imageMinimumGCAge: 2m0s
iptablesDropBit: 15
iptablesMasqueradeBit: 14
kind: KubeletConfiguration
kubeAPIBurst: 10
kubeAPIQPS: 5
makeIPTablesUtilChains: true
maxOpenFiles: 1000000
maxPods: 110
nodeStatusUpdateFrequency: 10s
oomScoreAdj: -999
podPidsLimit: -1
port: 10250
registryBurst: 10
registryPullQPS: 5
resolvConf: /etc/resolv.conf
rotateCertificates: true
runtimeRequestTimeout: 2m0s
serializeImagePulls: true
staticPodPath: /etc/kubernetes/manifests
streamingConnectionIdleTimeout: 4h0m0s
syncFrequency: 1m0s
volumeStatsAggPeriod: 1m0s
master $


这其中有一些需要关注的配置：


maxPods：最大的 Pod 数

healthzBindAddress 和 healthzPort：配置了健康检查所监听的地址和端口


我们可用以下方式进行验证：

  master $ curl 127.0.0.1:10248/healthz
  ok 



authentication 和 authorization ：认证授权相关

evictionHard：涉及到 kubelet 的驱逐策略，对 Pod 调度分配之类的影响很大


其余部分，可参考手册内容

Pod 管理

从上面的配置以及我们之前的介绍中，kube-scheduler 处理了 Pod 应该调度至哪个 Node，而 kubelet 则是保障该 Pod 能按照预期，在对应 Node 上启动并保持工作。

同时，kubelet 在保障 Pod 能按预期工作，主要是做了两方面的事情：


健康检查：通过 LivenessProbe 和 ReadinessProbe 探针进行检查，判断是否健康及是否已经准备好接受请求。
资源监控：通过 cAdvisor 进行资源监。


kubelet 是如何工作的

大致的功能已经介绍了，我们来看下它大体的实现。

首先是在 cmd/kubelet/app/server.go 文件中的 Run 方法：

func Run(s *options.KubeletServer, kubeDeps *kubelet.Dependencies, stopCh <-chan struct{}) error {
	glog.Infof("Version: %+v", version.Get())
	if err := initForOS(s.KubeletFlags.WindowsService); err != nil {
		return fmt.Errorf("failed OS init: %v", err)
	}
	if err := run(s, kubeDeps, stopCh); err != nil {
		return fmt.Errorf("failed to run Kubelet: %v", err)
	}
	return nil
}


这个方法看起来很简单那，它是在读取完一系列的配置和校验之后开始被调用的，在调用过程中，会在日志中输出当前的版本号，如果你的 kubelet 已经正常运行，当你执行 journalctl -u kubelet 的时候，便会看到一条相关的日志输出。

之后，便是一个 run 方法，其中包含着各种环境检查，容器管理，cAdvisor 初始化之类的操作，直到 kubelet 基本正确运行后，则会调用 pkg/kubelet/kubelet.go 中的一个 BirthCry 方法，该方法从命名就可以看出来，它其实就是宣告 kubelet 已经启动：

func (kl *Kubelet) BirthCry() {
	kl.recorder.Eventf(kl.nodeRef, v1.EventTypeNormal, events.StartingKubelet, "Starting kubelet.")
}


后续则是关于注册，Pod 管理以及资源相关的处理逻辑，内容较多，这里就不再展开了。

总结

本节中我们介绍了 kubelet 的主要功能和基本实现，了解到了它不仅可将自身注册到集群，同时还承担着保障 Pod 可在该 Node 上按预期工作。另外 kubelet 其实还承担着清理 Node 上一些由 K8S 调度 Pod 所造成的磁盘占用之类的工作。

从上面的配置中基本能看出来一些，这部分的功能大多数情况下不需要大家人为干预，所以也就不再展开了。

当 Pod 在 Node 上正常运行之后，若是需要对外提供服务，则需要将其暴露出来。下节，我们来介绍下 kube-proxy 是如何来处理这些工作的。

                        
                        
                            