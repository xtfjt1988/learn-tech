
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            14 庖丁解牛：controller-manager
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


在第 3 节《宏观认识：整体架构》 中，我们也认识到了 Controller Manager 的存在，知道了 Master 是 K8S 是集群的大脑，而它则是 Master 中最繁忙的部分。为什么这么说？本节我们一同来看看它为何如此繁忙。

注意：Controller Manager 实际由 kube-controller-manager 和 cloud-controller-manager 两部分组成，cloud-controller-manager 则是为各家云厂商提供了一个抽象的封装，便于让各厂商使用各自的 provide。本文只讨论 kube-controller-manager，为了避免混淆，下文统一使用 kube-controller-manager。

kube-controller-manager 是什么

一句话来讲 kube-controller-manager 是一个嵌入了 K8S 核心控制循环的守护进程。

这里的重点是


嵌入：它已经内置了相关逻辑，可独立进行部署。我们在第 5 节下载 K8S 服务端二进制文件解压后，便可以看到 kube-controller-manager 的可执行文件，不过我们使用的是 kubeadm 进行的部署，它会默认使用 k8s.gcr.io/kube-controller-manager 的镜像。我们直接来看下实际情况：


master $ kubectl -n kube-system describe pods -l component=kube-controller-manager
Name:               kube-controller-manager-master
Namespace:          kube-system
Priority:           2000000000
PriorityClassName:  system-cluster-critical
Node:               master/172.17.0.35
Start Time:         Mon, 10 Dec 2018 07:14:21 +0000
Labels:             component=kube-controller-manager
                    tier=control-plane
Annotations:        kubernetes.io/config.hash=c7ed7a8fa5c430410e84970f8ee7e067
                    kubernetes.io/config.mirror=c7ed7a8fa5c430410e84970f8ee7e067
                    kubernetes.io/config.seen=2018-12-10T07:14:21.685626322Z
                    kubernetes.io/config.source=file
                    scheduler.alpha.kubernetes.io/critical-pod=
Status:             Running
IP:                 172.17.0.35
Containers:
  kube-controller-manager:
    Container ID:  docker://0653e71ae4287608726490b724c3d064d5f1556dd89b7d3c618e97f0e7f2a533
    Image:         k8s.gcr.io/kube-controller-manager-amd64:v1.11.3
    Image ID:      docker-pullable://k8s.gcr.io/kube-controller-manager-amd64@sha256:a6d115bb1c0116036ac6e6e4d504665bc48879c421a450566c38b3b726f0a123
    Port:          <none>
    Host Port:     <none>
    Command:
      kube-controller-manager
      --address=127.0.0.1
      --cluster-signing-cert-file=/etc/kubernetes/pki/ca.crt
      --cluster-signing-key-file=/etc/kubernetes/pki/ca.key
      --controllers=*,bootstrapsigner,tokencleaner
      --kubeconfig=/etc/kubernetes/controller-manager.conf
      --leader-elect=true
      --root-ca-file=/etc/kubernetes/pki/ca.crt
      --service-account-private-key-file=/etc/kubernetes/pki/sa.key
      --use-service-account-credentials=true
    State:          Running
      Started:      Mon, 10 Dec 2018 07:14:24 +0000
    Ready:          True
    Restart Count:  0
    Requests:
      cpu:        200m
    Liveness:     http-get http://127.0.0.1:10252/healthz delay=15s timeout=15s period=10s #success=1 #failure=8
    Environment:  <none>
    Mounts:
      /etc/ca-certificates from etc-ca-certificates (ro)
      /etc/kubernetes/controller-manager.conf from kubeconfig (ro)
      /etc/kubernetes/pki from k8s-certs (ro)
      /etc/ssl/certs from ca-certs (ro)
      /usr/libexec/kubernetes/kubelet-plugins/volume/exec from flexvolume-dir (rw)
      /usr/local/share/ca-certificates from usr-local-share-ca-certificates (ro)
      /usr/share/ca-certificates from usr-share-ca-certificates (ro)
Conditions:
  Type              Status
  Initialized       True
  Ready             True
  ContainersReady   True
  PodScheduled      True
Volumes:
  usr-share-ca-certificates:
    Type:          HostPath (bare host directory volume)
    Path:          /usr/share/ca-certificates
    HostPathType:  DirectoryOrCreate
  usr-local-share-ca-certificates:
    Type:          HostPath (bare host directory volume)
    Path:          /usr/local/share/ca-certificates
    HostPathType:  DirectoryOrCreate
  etc-ca-certificates:
    Type:          HostPath (bare host directory volume)
    Path:          /etc/ca-certificates
    HostPathType:  DirectoryOrCreate
  k8s-certs:
    Type:          HostPath (bare host directory volume)
    Path:          /etc/kubernetes/pki
    HostPathType:  DirectoryOrCreate
  ca-certs:
    Type:          HostPath (bare host directory volume)
    Path:          /etc/ssl/certs
    HostPathType:  DirectoryOrCreate
  kubeconfig:
    Type:          HostPath (bare host directory volume)
    Path:          /etc/kubernetes/controller-manager.conf
    HostPathType:  FileOrCreate
  flexvolume-dir:
    Type:          HostPath (bare host directory volume)
    Path:          /usr/libexec/kubernetes/kubelet-plugins/volume/exec
    HostPathType:  DirectoryOrCreate
QoS Class:         Burstable
Node-Selectors:    <none>
Tolerations:       :NoExecute
Events:            <none>
master


这是使用 kubeadm 搭建的集群中的 kube-controller-manager 的 Pod，首先可以看到它所使用的镜像，其次可以看到它使用的一系列参数，最后它在 10252 端口提供了健康检查的接口。稍后我们再展开。


控制循环：这里拆解为两部分： 控制 和 循环 ，它所控制的是集群的状态；至于循环它当然是会有个循环间隔的，这里有个参数可以进行控制。
守护进程：这个就不单独展开了。


kube-controller-manager 有什么作用

前面已经说了它一个很关键的点 “控制”：它通过 kube-apiserver 提供的信息持续的监控集群状态，并尝试将集群调整至预期的状态。由于访问 kube-apiserver 也需要通过认证，授权等过程，所以可以看到上面启动 kube-controller-manager 时提供了一系列的参数。

比如，当我们创建了一个 Deployment，默认副本数为 1 ，当我们把 Pod 删除后，kube-controller-manager 会按照原先的预期，重新创建一个 Pod 。下面举个例子：

master $ kubectl run redis --image='redis'
deployment.apps/redis created
master $ kubectl get all
NAME                        READY     STATUS    RESTARTS   AGE
pod/redis-bb7894d65-w2rsp   1/1       Running   0          3m

NAME                 TYPE        CLUSTER-IP   EXTERNAL-IP   PORT(S)   AGE
service/kubernetes   ClusterIP   10.96.0.1    <none>        443/TCP   18m

NAME                    DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/redis   1         1         1            1           3m

NAME                              DESIRED   CURRENT   READY     AGE
replicaset.apps/redis-bb7894d65   1         1         1         3m
master $ kubectl delete pod/redis-bb7894d65-w2rsp
pod "redis-bb7894d65-w2rsp" deleted
master $ kubectl get all  # 可以看到已经重新运行了一个 Pod
NAME                        READY     STATUS    RESTARTS   AGE
pod/redis-bb7894d65-62ftk   1/1       Running   0          16s

NAME                 TYPE        CLUSTER-IP   EXTERNAL-IP   PORT(S)   AGE
service/kubernetes   ClusterIP   10.96.0.1    <none>        443/TCP   19m

NAME                    DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/redis   1         1         1            1           4m

NAME                              DESIRED   CURRENT   READY     AGE
replicaset.apps/redis-bb7894d65   1         1         1         4m


我们来看下 kube-controller-manager 的日志：

master $ kubectl -n kube-system logs -l component=kube-controller-manager --tail=5
I1210 09:30:17.125377       1 node_lifecycle_controller.go:945] Controller detected that all Nodes are not-Ready. Entering master disruption mode.
I1210 09:31:07.140539       1 node_lifecycle_controller.go:972] Controller detected that some Nodes are Ready. Exiting master disruption mode.
I1210 09:43:30.377649       1 event.go:221] Event(v1.ObjectReference{Kind:"Deployment", Namespace:"default", Name:"redis", UID:"0d1cb2d7-fc60-11e8-a361-0242ac110074", APIVersion:"apps/v1", ResourceVersion:"1494", FieldPath:""}): type: 'Normal' reason: 'ScalingReplicaSet' Scaled up replica setredis-bb7894d65 to 1
I1210 09:43:30.835149       1 event.go:221] Event(v1.ObjectReference{Kind:"ReplicaSet", Namespace:"default", Name:"redis-bb7894d65", UID:"0d344d15-fc60-11e8-a361-0242ac110074", APIVersion:"apps/v1", ResourceVersion:"1495", FieldPath:""}): type: 'Normal' reason: 'SuccessfulCreate' Created pod:redis-bb7894d65-w2rsp
I1210 09:47:41.658781       1 event.go:221] Event(v1.ObjectReference{Kind:"ReplicaSet", Namespace:"default", Name:"redis-bb7894d65", UID:"0d344d15-fc60-11e8-a361-0242ac110074", APIVersion:"apps/v1", ResourceVersion:"1558", FieldPath:""}): type: 'Normal' reason: 'SuccessfulCreate' Created pod:redis-bb7894d65-62ftk


可以看到它先观察到有 Deployment 的事件，然后 ScalingReplicaSet 进而创建了对应的 Pod。 而当我们删掉正在运行的 Pod 后，它便会重新创建 Pod 使集群状态符合原先的预期状态。

同时，注意 Pod 的名字已经发生了变化。

kube-controller-manager 是如何工作的

在 cmd/kube-controller-manager/app/controllermanager.go 中列出了大多数的 controllermanager，他们对 controllermanager 函数的实际调用都在 cmd/kube-controller-manager/app/core.go 中，我们以 PodGC 为例：

func startPodGCController(ctx ControllerContext) (bool, error) {
	go podgc.NewPodGC(
		ctx.ClientBuilder.ClientOrDie("pod-garbage-collector"),
		ctx.InformerFactory.Core().V1().Pods(),
		int(ctx.ComponentConfig.PodGCController.TerminatedPodGCThreshold),
	).Run(ctx.Stop)
	return true, nil
}


在前两节中我们已经对 kube-apiserver 和 etcd 有了一些基本的认识，这里它主要会去 watch 相关的资源，但是出于性能上的考虑，也不能过于频繁的去请求 kube-apiserver 或者永久 watch ，所以在实现上借助了 client-go 的 informer 包，相当于是实现了一个本地的二级缓存。这里不做过多展开。

它最终会调用 PodGC 的具体实现，位置在 pkg/controller/podgc/gc_controller.go 中：

func NewPodGC(kubeClient clientset.Interface, podInformer coreinformers.PodInformer, terminatedPodThreshold int) *PodGCController {
	if kubeClient != nil && kubeClient.CoreV1().RESTClient().GetRateLimiter() != nil {
		metrics.RegisterMetricAndTrackRateLimiterUsage("gc_controller", kubeClient.CoreV1().RESTClient().GetRateLimiter())
	}
	gcc := &PodGCController{
		kubeClient:             kubeClient,
		terminatedPodThreshold: terminatedPodThreshold,
		deletePod: func(namespace, name string) error {
			glog.Infof("PodGC is force deleting Pod: %v:%v", namespace, name)
			return kubeClient.CoreV1().Pods(namespace).Delete(name, metav1.NewDeleteOptions(0))
		},
	}

	gcc.podLister = podInformer.Lister()
	gcc.podListerSynced = podInformer.Informer().HasSynced

	return gcc
}


代码也比较直观，不过这里可以看到有一个注册 metrics 的过程，实际上 kube-controller-manager 在前面的 10252 端口上不仅暴露出来了一个 /healthz 接口，还暴露出了一个 /metrics 的接口，可用于进行监控之类的。

master $ kubectl -n kube-system get pod -l component=kube-controller-manager
NAME                             READY     STATUS    RESTARTS   AGE
kube-controller-manager-master   1/1       Running   1          2m
master $ kubectl -n kube-system exec -it kube-controller-manager-master sh
/ # wget -qO- http://127.0.0.1:10252/metrics|grep gc_controller
# HELP gc_controller_rate_limiter_use A metric measuring the saturation of the rate limiter for gc_controller
# TYPE gc_controller_rate_limiter_use gauge
gc_controller_rate_limiter_use 0


总结

在本节中，我们介绍了 kube-controller-manager 以及它在 K8S 中主要是将集群调节至预期的状态，并提供出了 /metrics 的接口可供监控。

kube-controller-manager 中有很多的 controller 大多数是默认开启的，当然也有默认关闭的，比如 bootstrapsigner 和 tokencleaner，在我们启动 kube-controller-manager 的时候，可通过 --controllers 的参数进行控制，就比如上面例子中 --controllers=*,bootstrapsigner,tokencleaner 表示开启所有默认开启的以及 bootstrapsigner 和 tokencleaner 。

下节，我们将学习另一个与资源调度有关的组件 kube-scheduler，了解下它对我们使用集群所带来的意义。

                        
                        
                            