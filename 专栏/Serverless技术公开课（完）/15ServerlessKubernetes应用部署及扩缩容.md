
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            15 Serverless Kubernetes 应用部署及扩缩容
                            导读：本文分为三个部分，首先给大家演示 Serverless Kubernetes 集群的创建和业务应用的部署，其次介绍 Serverless Kubernetes 的常用功能，最后对应用扩缩容的操作进行探讨。

集群创建及应用部署

1. 集群创建

在对 Serverless Kubernetes 的基础概念有了充分了解之后，我们直接进入容器服务控制台（https://cs.console.aliyun.com/#/authorize）进行集群的创建。



在创建页面，主要有三类属性需要选择或填写：


集群创建的地域和 Kubernetes 的版本信息；
网络属性：可以选择容器服务自动创建或者指定已有的 VPC 资源；
集群能力和服务：可以按需选择。


属性完成后，点击“创建集群”即可，整个创建过程需要 1~2 分钟的时间。



2. 应用部署

集群创建完成后，接下来我们部署一个无状态的 nginx 应用，主要分成三步：


应用基本信息：名称、POD 数量、标签等；
容器配置：镜像、所需资源、容器端口、数据卷等；
高级配置：服务、路由、HPA、POD 标签等。




创建完成后，在路由中就可以看到服务对外暴露的访问方式了。



如上图所示，在本地 host 绑定 ask-demo.com 到路由端点 123.57.252.131 的解析，然后浏览器访问域名，即可请求到部署的 nginx 应用。

常用功能介绍

我们一般会通过容器服务控制台和 Kubectl 两种方式，来使用 Serverless Kubernetes 的常用功能。

1. 容器服务控制台



在容器服务控制台上，我们可以进行以下功能的白屏化操作：


基本信息：集群 ID 和运行状态、API Server 端点、VPC 和安全性、集群访问凭证的查看和操作；
存储卷：PV、PVC、StorageClass 的查看和操作；
命名空间：集群 namespace 的查看和操作；
工作负载：Deployment、StatefulSet、Job、CronJob、Pod 的查看和操作；
服务：工作负载提供出的 Service 的查看和操作；
路由：Ingress 的查看和操作，用来路由 Service；
发布：对基于 Helm 或者容器服务分批发布的任务进行查看和操作；
配置管理：对 ConfigMap 和 Secret 的查看和操作；
运维管理：集群的事件列表和操作审计。


2. Kubectl

除了通过控制台，我们还可以基于 Kubectl 来进行集群操作和管理。



我们可以在云端通过 CloudShell 来使用 Kubectl，也可以在本地安装 Kubectl，然后通过将集群的访问凭证写入到 kubeconfig 来使用 Serverless Kubernetes 。

应用弹性伸缩

通通过上面的内容讲解，我们已经了解了应用的部署和集群的常用操作，下面为大家介绍一下如何为应用做扩缩容操作。

在 Serverless Kubernetes 中常用的应用扩缩容方式包括：


人工扩缩容：最为原始的方式，在成本和应用稳定性上均有一定程度的牺牲；
HPA（Horizontal Pod Autoscaler）：根据 Cpu 和 Memory 等指标来弹性伸缩，适合有突发流量场景的应用；
Cron HPA ：根据 Cron 表达式来定期伸缩，适合有固定波峰波谷特性的应用；
External Metrics（alibaba-cloud-metrics-adapter）：阿里云指标容器水平伸缩，在原生 HPA 的基础上支持更多的数据指标。


结语

以上就是 Serverless Kubernetes 应用部署及扩缩容的全部分享，希望通过这次分享能够帮助大家更好地入门和使用 Serverless Kubernetes，后续也将会有更多的 Serverless Kubernetes 的实践案例分享给大家。

                        
                        
                            