
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            10 应用管理：初识 Helm
                            整体概览

上节，我们已经学习了如何通过编写配置文件的方式部署项目。而在实际生产环境中，项目所包含组件可能不止 3 个，并且可能项目数会很多，如果每个项目的发布，更新等都通过手动去编写配置文件的方式，实在不利于管理。

并且，当线上出现个别组件升级回滚之类的操作，如果组件之间有相关版本依赖等情况，那事情会变得复杂的多。我们需要有更简单的机制来辅助我们完成这些事情。

Helm 介绍

Helm 是构建于 K8S 之上的包管理器，可与我们平时接触到的 Yum，APT，Homebrew 或者 Pip 等包管理器相类比。

使用 Helm 可简化包分发，安装，版本管理等操作流程。同时它也是 CNCF 孵化项目。

Helm 安装

Helm 是 C/S 架构，主要分为客户端 helm 和服务端 Tiller。安装时可直接在 Helm 仓库的 Release 页面 下载所需二进制文件或者源码包。

由于当前项目的二进制文件存储已切换为 GCS，我已经为国内用户准备了最新版本的二进制包，可通过以下链接进行下载。

链接: https://pan.baidu.com/s/1n1zj3rlv2NyfiA6kRGrHfg 提取码: 5huw 


下载后对文件进行解压，我这里以 Linux amd64 为例。

➜  /tmp tar -zxvf helm-v2.11.0-linux-amd64.tar.gz
linux-amd64/
linux-amd64/tiller
linux-amd64/README.md
linux-amd64/helm
linux-amd64/LICENSE
➜  /tmp tree linux-amd64 
linux-amd64
├── helm
├── LICENSE
├── README.md
└── tiller

0 directories, 4 files


解压完成后，可看到其中包含 helm 和 tiller 二进制文件。

客户端 helm

helm 是个二进制文件，直接将它移动至 /usr/bin 目录下即可。

➜  /tmp sudo mv linux-amd64/helm /usr/bin/helm 


这时候便可直接通过 helm 命令使用了。比如，我们验证当前使用的版本。

➜  /tmp helm version
Client: &version.Version{SemVer:"v2.11.0", GitCommit:"2e55dbe1fdb5fdb96b75ff144a339489417b146b", GitTreeState:"clean"}
Error: Get http://localhost:8080/api/v1/namespaces/kube-system/pods?labelSelector=app%3Dhelm%2Cname%3Dtiller: dial tcp 127.0.0.1:8080: connect: connection refused


可以看到上面有明显的报错，并且很像 kubectl 未正确配置时的错误。这是因为 helm 默认会去读取 $HOME/.kube/config 的配置文件，用于正确的连接至目标集群。

当我们正确的配置好 $HOME/.kube/config 文件时，再次执行：

➜  /tmp helm version
Client: &version.Version{SemVer:"v2.11.0", GitCommit:"2e55dbe1fdb5fdb96b75ff144a339489417b146b", GitTreeState:"clean"}
Error: could not find tiller


这次报错是因为找不到服务端 Tiller，接下来我们部署服务端。

服务端 Tiller

以下讨论中，前提都是 $HOME/.kube/config 已正确配置，并且 kebectl 有操作集群的权限。

本地安装

刚才我们解压的文件中，还有一个二进制文件 tiller 。我们可以直接执行它，用于在本地启动服务。

➜  /tmp ./linux-amd64/tiller
[main] 2018/11/18 23:47:10 Starting Tiller v2.11.0 (tls=false)
[main] 2018/11/18 23:47:10 GRPC listening on :44134
[main] 2018/11/18 23:47:10 Probes listening on :44135
[main] 2018/11/18 23:47:10 Storage driver is ConfigMap
[main] 2018/11/18 23:47:10 Max history per release is 0


直接执行时，默认会监听 44134 和 44135 端口，44134 端口用于和 helm 进行通信，而 44135 主要是用于做探活的，在部署至 K8S 时使用。

当我们使用客户端连接时，只需设置 HELM_HOST 环境变量即可。

➜  ~ export HELM_HOST=localhost:44134
➜  ~ helm version
Client: &version.Version{SemVer:"v2.11.0", GitCommit:"2e55dbe1fdb5fdb96b75ff144a339489417b146b", GitTreeState:"clean"}
Server: &version.Version{SemVer:"v2.11.0", GitCommit:"2e55dbe1fdb5fdb96b75ff144a339489417b146b", GitTreeState:"clean"}


注意 一定要正确配置 $HOME/.kube/config 文件，否则会影响正常功能使用。

默认安装

官方提供了一种一键式安装的方式。那便是 helm init 执行这条命令后，会同时在 K8S 中部署服务端 Tiller 和初始化 helm 的默认目录 $HELM_HOME 默认值为 $HOME/.helm。

这种方式下会默认使用官方镜像 gcr.io/kubernetes-helm/tiller 网络原因可能会导致安装失败。所以我已将官方镜像进行同步。可使用以下方式进行使用：

➜  ~ helm init --tiller-image taobeier/tiller:v2.11.0 
Creating /root/.helm
Creating /root/.helm/repository
Creating /root/.helm/repository/cache
Creating /root/.helm/repository/local
Creating /root/.helm/plugins
Creating /root/.helm/starters
Creating /root/.helm/cache/archive
Creating /root/.helm/repository/repositories.yaml
Adding stable repo with URL: https://kubernetes-charts.storage.googleapis.com
Adding local repo with URL: http://127.0.0.1:8879/charts
$HELM_HOME has been configured at /root/.helm.

Tiller (the Helm server-side component) has been installed into your Kubernetes Cluster.

Please note: by default, Tiller is deployed with an insecure 'allow unauthenticated users' policy.
To prevent this, run `helm init` with the --tiller-tls-verify flag.
For more information on securing your installation see: https://docs.helm.sh/using_helm/#securing-your-helm-installation
Happy Helming!
➜  ~ helm version
Client: &version.Version{SemVer:"v2.11.0", GitCommit:"9ad53aac42165a5fadc6c87be0dea6b115f93090", GitTreeState:"clean"}
Server: &version.Version{SemVer:"v2.11.0", GitCommit:"2e55dbe1fdb5fdb96b75ff144a339489417b146b", GitTreeState:"clean"}


可以看到 $HELM_HOME 目录已经初始化完成，客户端与服务端已可以正常通信。查看下当前 K8S 集群中的情况：

➜  ~ kubectl -n kube-system get deploy tiller-deploy
NAME            DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
tiller-deploy   1         1         1            1           6m


可以看到已正常部署。

手动安装

通过上面的描述，可能你已经发现，安装服务端，其实也就是一次普通的部署，我们可以通过以下方式来自行通过 kubectl 完成部署。

➜  ~ helm init --dry-run --debug  # 篇幅原因，以下内容进行了省略
---                               
apiVersion: extensions/v1beta1
kind: Deployment                                    
metadata:                            
  creationTimestamp: null
  labels:         
    app: helm              
    name: tiller       
  name: tiller-deploy           
  namespace: kube-system   
spec:               
  replicas: 1 
  strategy: {}                
  ...
status: {}

---
apiVersion: v1
kind: Service
metadata:
  creationTimestamp: null
  labels:
    app: helm
    name: tiller
  name: tiller-deploy
  namespace: kube-system
spec:
  ports:
  - name: tiller
    port: 44134
    targetPort: tiller
  selector:
    app: helm
    name: tiller
  type: ClusterIP
status:
  loadBalancer: {}


将输出内容保存至文件中，自行修改后，通过 kubectl 进行部署即可。建议在修改过程中，尽量不要去更改标签及选择器。

RBAC 使用

上面的内容中，均未提及到权限控制相关的内容，但是在生产环境中使用，我们一般都是会进行权限控制的。

在第 8 节中，我们已经详细的解释了认证授权相关的内容。所以下面的内容不做太多详细解释。

这里我们创建一个 ServiceAccount 命名为 tiller，为了简单，我们直接将它与 cluster-admin 进行绑定。

apiVersion: v1
kind: ServiceAccount
metadata:
  name: tiller
  namespace: kube-system
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRoleBinding
metadata:
  name: tiller
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: ClusterRole
  name: cluster-admin
subjects:
  - kind: ServiceAccount
    name: tiller
    namespace: kube-system


将此内容保存为 tiller-rbac.yaml，开始进行部署操作。

➜  ~ kubectl apply -f tiller-rbac.yaml
serviceaccount/tiller created
clusterrolebinding.rbac.authorization.k8s.io/tiller created
➜  ~ helm init --service-account tiller
Creating /root/.helm
Creating /root/.helm/repository
Creating /root/.helm/repository/cache
Creating /root/.helm/repository/local
Creating /root/.helm/plugins
Creating /root/.helm/starters
Creating /root/.helm/cache/archive
Creating /root/.helm/repository/repositories.yaml
Adding stable repo with URL: https://kubernetes-charts.storage.googleapis.com
Adding local repo with URL: http://127.0.0.1:8879/charts
$HELM_HOME has been configured at /root/.helm.

Tiller (the Helm server-side component) has been installed into your Kubernetes Cluster.

Please note: by default, Tiller is deployed with an insecure 'allow unauthenticated users' policy.
To prevent this, run `helm init` with the --tiller-tls-verify flag.
For more information on securing your installation see: https://docs.helm.sh/using_helm/#securing-your-helm-installation
Happy Helming!
➜  ~ helm version
Client: &version.Version{SemVer:"v2.11.0", GitCommit:"2e55dbe1fdb5fdb96b75ff144a339489417b146b", GitTreeState:"clean"}
Server: &version.Version{SemVer:"v2.11.0", GitCommit:"2e55dbe1fdb5fdb96b75ff144a339489417b146b", GitTreeState:"clean"}


以此方式完成部署。

Helm 概念

Chart

chart 就是 Helm 所管理的包，类似于 Yum 所管理的 rpm 包或是 Homebrew 管理的 Formulae。它包含着一个应用要部署至 K8S 上所必须的所有资源。

Release

Release 就是 chart 在 K8S 上部署后的实例。chart 的每次部署都将产生一次 Release。这和上面类比的包管理器就有所不同了，多数的系统级包管理器所安装的包只会在系统中存在一份。我们可以以 Pip 在虚拟环境下的包安装，或者 Npm 的 local install 来进行类比。

Repository

Repository 就是字面意思，存储 chart 的仓库。还记得我们上面执行 helm init 时的输出吗？默认情况下，初始化 Helm 的时候，会添加两个仓库，一个是 stable 仓库 kubernetes-charts.storage.googleapis.com 另一个则是 local 仓库，地址是 http://127.0.0.1:8879/charts 。

Config

前面提到了 chart 是应用程序所必须的资源，当然我们实际部署的时候，可能就需要有些自定义的配置了。Config 便是用于完成此项功能的，在部署时候，会将 config 与 chart 进行合并，共同构成我们将部署的应用。

Helm 的工作原理

helm 通过 gRPC 将 chart 发送至 Tiller ，Tiller 则通过内置的 kubernetes 客户端库与 K8S 的 API server 进行交流，将 chart 进行部署，并生成 Release 用于管理。

前面只说到了 helm 与 Tiller 交互的协议，但尚未说其数据链路。

我们来看看 Tiller 的部署情况。主要看 Service：

➜  ~ kubectl -n kube-system get svc
NAME            TYPE        CLUSTER-IP       EXTERNAL-IP   PORT(S)         AGE
kube-dns        ClusterIP   10.96.0.10       <none>        53/UDP,53/TCP   1h
tiller-deploy   ClusterIP   10.107.204.164   <none>        44134/TCP       33m


Tiller 默认采用 ClusterIP 类型的 Service 进行部署。而我们知道的 ClusterIP 类型的 Service 是仅限集群内访问的。

在这里所依赖的技术，便是在第 5 节，我们提到的 socat 。helm 通过 socat 的端口转发（或者说 K8S 的代理），进而实现了本地与 Tiller 的通信。

当然，以上内容均以当前最新版本 2.11.0 为例。当下一个大版本 Helm v3 出现时， Tiller 将不复存在，通信机制和工作原理也将发生变化。

总结

通过本节，我们已经学习到了 Helm 的基础知识和工作原理，了解到了 Helm 的用途以及如何在本地和 K8S 中部署它。需要注意的是 $HOME/.kube/config 需要提前配置好，以及 socat 工具需要提前安装，可参考第 5 节的内容。

接下来，我们将上节中的示例项目使用 Helm 部署至 K8S 集群中。	

                        
                        
                            