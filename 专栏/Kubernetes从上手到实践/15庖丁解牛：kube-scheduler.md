
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            15 庖丁解牛：kube-scheduler
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


在第 3 节《宏观认识：整体架构》 中，我们也认识到了 Scheduler 的存在，知道了 Master 是 K8S 是集群的大脑，Controller Manager 负责将集群调整至预期的状态，而 Scheduler 则是集群调度器，将预期的 Pod 资源调度到正确的 Node 节点上，进而令该 Pod 可完成启动。本节我们一同来看看它如何发挥如此大的作用。

下文统一使用 kube-scheduler 进行表述

kube-scheduler 是什么

引用官方文档一句话：


The Kubernetes scheduler is a policy-rich, topology-aware, workload-specific function that significantly impacts availability, performance, and capacity.


kube-scheduler 是一个策略丰富，拓扑感知的调度程序，会显著影响可用性，性能和容量。

我们知道资源调度本就是 K8S 这类系统中的一个很复杂的事情，既要能满足系统对资源利用率的需要，同样还需要避免资源竞争，比如说端口冲突之类的。

为了能完成这样的需求，kube-scheduler 便在不断的迭代和发展，通过支持多种策略满足各类需求，通过感知拓扑避免资源竞争和保障系统的可用性及容量等。

我们在第 5 节下载服务端二进制文件解压后，便可看到 kube-scheduler 的可执行文件。当给它传递 --help 查看其支持参数的时候，便可以看到它支持使用 --address 或者 --bind-address 等参数指定所启动的 HTTP server 所绑定的地址之类的。

它和 kube-controller-manager 有点类似，同样是通过定时的向 kube-apiserver 请求获取信息，并进行处理。而他们所起到的作用并不相同。

kube-scheduler 有什么作用

从上层的角度来看，kube-scheduler 的作用就是将待调度的 Pod 调度至最佳的 Node 上，而这个过程中则需要根据不同的策略，考虑到 Node 的资源使用情况，比如端口，内存，存储等。

kube-scheduler 是如何工作的

整体的过程可通过 pkg/scheduler/core/generic_scheduler.go 的代码来看

func (g *genericScheduler) Schedule(pod *v1.Pod, nodeLister algorithm.NodeLister) (string, error) {
	trace := utiltrace.New(fmt.Sprintf("Scheduling %s/%s", pod.Namespace, pod.Name))
	defer trace.LogIfLong(100 * time.Millisecond)

	if err := podPassesBasicChecks(pod, g.pvcLister); err != nil {
		return "", err
	}

	nodes, err := nodeLister.List()
	if err != nil {
		return "", err
	}
	if len(nodes) == 0 {
		return "", ErrNoNodesAvailable
	}

	err = g.cache.UpdateNodeNameToInfoMap(g.cachedNodeInfoMap)
	if err != nil {
		return "", err
	}

	trace.Step("Computing predicates")
	startPredicateEvalTime := time.Now()
	filteredNodes, failedPredicateMap, err := g.findNodesThatFit(pod, nodes)
	if err != nil {
		return "", err
	}

	if len(filteredNodes) == 0 {
		return "", &FitError{
			Pod:              pod,
			NumAllNodes:      len(nodes),
			FailedPredicates: failedPredicateMap,
		}
	}
	metrics.SchedulingAlgorithmPredicateEvaluationDuration.Observe(metrics.SinceInMicroseconds(startPredicateEvalTime))
	metrics.SchedulingLatency.WithLabelValues(metrics.PredicateEvaluation).Observe(metrics.SinceInSeconds(startPredicateEvalTime))

	trace.Step("Prioritizing")
	startPriorityEvalTime := time.Now()
	// When only one node after predicate, just use it.
	if len(filteredNodes) == 1 {
		metrics.SchedulingAlgorithmPriorityEvaluationDuration.Observe(metrics.SinceInMicroseconds(startPriorityEvalTime))
		return filteredNodes[0].Name, nil
	}

	metaPrioritiesInterface := g.priorityMetaProducer(pod, g.cachedNodeInfoMap)
	priorityList, err := PrioritizeNodes(pod, g.cachedNodeInfoMap, metaPrioritiesInterface, g.prioritizers, filteredNodes, g.extenders)
	if err != nil {
		return "", err
	}
	metrics.SchedulingAlgorithmPriorityEvaluationDuration.Observe(metrics.SinceInMicroseconds(startPriorityEvalTime))
	metrics.SchedulingLatency.WithLabelValues(metrics.PriorityEvaluation).Observe(metrics.SinceInSeconds(startPriorityEvalTime))

	trace.Step("Selecting host")
	return g.selectHost(priorityList)
}


它的输入有两个：


pod：待调度的 Pod 对象；
nodeLister：所有可用的 Node 列表


备注：nodeLister 的实现稍微用了点技巧，返回的是 []*v1.Node 而不是 v1.NodeList 可避免拷贝带来的性能损失。

type NodeLister interface {
	List() ([]*v1.Node, error)
}


处理阶段

kube-scheduler 将处理阶段主要分为三个阶段 Computing predicates，Prioritizing和 Selecting host：


Computing predicates：主要解决的问题是 Pod 能否调度到集群的 Node 上；


主要是通过一个名为 podFitsOnNode 的函数进行实现，在检查的过程中也会先去检查下是否已经有已缓存的判断结果， 当然也会检查 Pod 是否是可调度的，以防有 Pod Affinity (亲合性) 之类的存在。


Prioritizing：主要解决的问题是在上个阶段通过 findNodesThatFit 得到了 filteredNodes 的基础之上解决哪些 Node 是最优的，得到一个优先级列表 priorityList;


至于优先级的部分，主要是通过下面的代码：

  for i := range nodes {
  	result = append(result, schedulerapi.HostPriority{Host: nodes[i].Name, Score: 0})
  	for j := range priorityConfigs {
  		result[i].Score += results[j][i].Score * priorityConfigs[j].Weight
  	}
  }


给每个经过第一步筛选出来的 Node 一个 Score，再按照各种条件进行打分，最终得到一个优先级列表。


Selecting host：则是最终选择 Node 调度到哪台机器上。


最后，则是通过 selectHost 选择出最终要调度到哪台机器上。

  func (g *genericScheduler) selectHost(priorityList schedulerapi.HostPriorityList) (string, error) {
      if len(priorityList) == 0 {
          return "", fmt.Errorf("empty priorityList")
      }
  
      sort.Sort(sort.Reverse(priorityList))
      maxScore := priorityList[0].Score
      firstAfterMaxScore := sort.Search(len(priorityList), func(i int) bool { return priorityList[i].Score < maxScore })
  
      g.lastNodeIndexLock.Lock()
      ix := int(g.lastNodeIndex % uint64(firstAfterMaxScore))
      g.lastNodeIndex++
      g.lastNodeIndexLock.Unlock()
  
      return priorityList[ix].Host, nil
  }


总结

在本节中，我们介绍了 kube-scheduler 以及它在调度 Pod 的过程中的大致步骤。

不过它实际使用的各种策略及判断条件很多，无法在一节中完全都详细介绍，感兴趣的朋友可以按照本节中提供的思路大致去看看它的实现。

我们通过前面几节的介绍，已经知道了当实际进行部署操作的时候，首先会通过 kubectl 之类的客户端工具与 kube-apiserver 进行交互，在经过一系列的处理后，数据将持久化到 etcd 中；

此时，kube-controller-manager 通过持续的观察，开始按照我们的配置，将集群的状态调整至预期状态；

而 kube-scheduler 也在发挥作用，决定 Pod 应该调度至哪个或者哪些 Node 上；之后则通过其他组件的协作，最总将该 Pod 在相应的 Node 上部署启动。

我们在下节将要介绍的 kubelet 便是后面这部分“实际部署动作”相关的组件中尤为重要的一个，下节我们再详细介绍它是如何完成这些功能的。

                        
                        
                            