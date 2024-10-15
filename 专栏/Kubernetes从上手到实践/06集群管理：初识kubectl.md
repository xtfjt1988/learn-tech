
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            06 集群管理：初识 kubectl
                            从本节开始，我们来学习 K8S 集群管理相关的知识。通过前面的学习，我们知道 K8S 遵循 C/S 架构，官方也提供了 CLI 工具 kubectl 用于完成大多数集群管理相关的功能。当然凡是你可以通过 kubectl 完成的与集群交互的功能，都可以直接通过 API 完成。

对于我们来说 kubectl 并不陌生，在第 3 章讲 K8S 整体架构时，我们首次提到了它。在第 4 章和第 5 章介绍了两种安装 kubectl 的方式故而本章不再赘述安装的部分。

整体概览

首先我们在终端下执行下 kubectl:

➜  ~ kubectl                
kubectl controls the Kubernetes cluster manager.
...
Usage:
  kubectl [flags] [options]


kubectl 已经将命令做了基本的归类，同时显示了其一般的用法 kubectl [flags] [options] 。

使用 kubectl options 可以看到所有全局可用的配置项。

基础配置

在我们的用户家目录，可以看到一个名为 .kube/config 的配置文件，我们来看下其中的内容（此处以本地的 minikube 集群为例）。

➜  ~ ls $HOME/.kube/config 
/home/tao/.kube/config
➜  ~ cat $HOME/.kube/config
apiVersion: v1
clusters:
- cluster:
    certificate-authority: /home/tao/.minikube/ca.crt
    server: https://192.168.99.101:8443
  name: minikube
contexts:
- context:
    cluster: minikube
    user: minikube
  name: minikube
current-context: minikube
kind: Config
preferences: {}
users:
- name: minikube
  user:
    client-certificate: /home/tao/.minikube/client.crt
    client-key: /home/tao/.minikube/client.key


$HOME/.kube/config 中主要包含着：


K8S 集群的 API 地址
用于认证的证书地址


当然，我们在第 5 章时，也已经说过，也可以使用 --kubeconfig 或者环境变量 KUBECONFIG 来传递配置文件。

另外如果你并不想使用配置文件的话，你也可以通过使用直接传递相关参数来使用，例如：

➜  ~ kubectl --client-key='/home/tao/.minikube/client.key' --client-certificate='/home/tao/.minikube/client.crt' --server='https://192.168.99.101:8443'  get nodes
NAME       STATUS    ROLES     AGE       VERSION
minikube   Ready     master    2d        v1.11.3


从 get 说起

无论是第 4 章还是第 5 章，当我们创建集群后，我们都做了两个相同的事情，一个是执行 kubectl get nodes 另一个则是 kubectl cluster-info，我们先从查看集群内 Node 开始。

这里我们使用了一个本地已创建好的 minikube 集群。

➜  ~ kubectl get nodes
NAME       STATUS    ROLES     AGE       VERSION
minikube   Ready     master    2d        v1.11.3
➜  ~ kubectl get node
NAME       STATUS    ROLES     AGE       VERSION
minikube   Ready     master    2d        v1.11.3
➜  ~ kubectl get no
NAME       STATUS    ROLES     AGE       VERSION
minikube   Ready     master    2d        v1.11.3


可以看到以上三种“名称”均可获取当前集群内 Node 信息。这是为了便于使用而增加的别名和缩写。

如果我们想要看到更详细的信息呢？可以通过传递 -o 参数以得到不同格式的输出。

➜  ~ kubectl get nodes -o wide 
NAME       STATUS    ROLES     AGE       VERSION   INTERNAL-IP   EXTERNAL-IP   OS-IMAGE            KERNEL-VERSION   CONTAINER-RUNTIME
minikube   Ready     master    2d        v1.11.3   10.0.2.15     <none>        Buildroot 2018.05   4.15.0           docker://17.12.1-ce


当然也可以传递 -o yaml 或者 -o json 得到更加详尽的信息。

使用 -o json 将内容以 JSON 格式输出时，可以配合 jq 进行内容提取。例如：

➜  ~ kubectl get nodes -o json | jq ".items[] | {name: .metadata.name} + .status.nodeInfo"
{
  "name": "minikube",
  "architecture": "amd64",
  "bootID": "d675d75b-e58e-40db-8910-6e5dda9e7cf9",
  "containerRuntimeVersion": "docker://17.12.1-ce",
  "kernelVersion": "4.15.0",
  "kubeProxyVersion": "v1.11.3",
  "kubeletVersion": "v1.11.3",
  "machineID": "078e2d22629747178397e29cf1c96cc7",
  "operatingSystem": "linux",
  "osImage": "Buildroot 2018.05",
  "systemUUID": "4073906D-69A1-46EE-A08C-0252D9F79893"
}


以此方法可得到 Node 的基础信息。

那么除了 Node 外我们还可以查看那些资源或别名呢？可以通过 kubectl api-resources 查看服务端支持的 API 资源及别名和描述等信息。

答疑解惑 explain

当通过上面的命令拿到所有支持的 API 资源列表后，虽然后面基本都有一个简单的说明，是不是仍然感觉一头雾水？

别担心，在我们使用 Linux 的时候，我们有 man ，在使用 kubectl 的时候，我们除了 --help 外还有 explain 可帮我们进行说明。 例如：

➜  ~ kubectl explain node                                                                                                              
KIND:     Node
VERSION:  v1

DESCRIPTION:              
     Node is a worker node in Kubernetes. Each node will have a unique
     identifier in the cache (i.e. in etcd).

# ... 省略输出


总结

本节我们大致介绍了 kubectl 的基础使用，尤其是最常见的 get 命令。可通过传递不同参数获取不同格式的输出，配合 jq 工具可方便的进行内容提取。

以及关于 kubectl 的配置文件和无配置文件下通过传递参数直接使用等。

对应于我们前面提到的 K8S 架构，本节相当于 CURD 中的 R 即查询。查询对于我们来说，既是我们了解集群的第一步，同时也是后续验证操作结果或集群状态必不可少的技能。

当然，你在集群管理中可能会遇到各种各样的问题，单纯依靠 get 并不足以定位问题，我们在第 21 节中将介绍 Troubleshoot 的思路及方法。

下节我们来学习关于 C 的部分，即创建。

                        
                        
                            