
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            11 部署实践：以 Helm 部署项目
                            概览

上节，我们学习到了 Helm 的基础概念和工作原理，本节我们将 Helm 用于我们的实际项目，编写 Helm chart 以及通过 Helm 进行部署。

Helm chart

上节我们解释过 chart 的含义，现在我们要将项目使用 Helm 部署，那么首先，我们需要创建一个 chart。

Chart 结构

在我们项目的根目录下，通过以下命令创建一个 chart。

➜  saythx git:(master) helm create saythx
Creating saythx
➜  saythx git:(master) ✗ tree -a saythx
saythx
├── charts
├── Chart.yaml
├── .helmignore
├── templates
│   ├── deployment.yaml
│   ├── _helpers.tpl
│   ├── ingress.yaml
│   ├── NOTES.txt
│   └── service.yaml
└── values.yaml

2 directories, 8 files


创建完成后，我们可以看到默认创建的 chart 中包含了几个文件和目录。我们先对其进行解释。

Chart.yaml

➜  saythx git:(master) ✗ cat saythx/Chart.yaml
apiVersion: v1
appVersion: "1.0"
description: A Helm chart for Kubernetes
name: saythx
version: 0.1.0


这个文件是每个 chart 必不可少的一个文件，其中包含着几个重要的属性，如：


apiVersion：目前版本都为 v1
appVersion：这是应用的版本号，需要与 apiVersion， version 等字段注意区分
name: 通常要求 chart 的名字必须和它所在目录保持一致，且此字段必须
version：表明当前 chart 的版本号，会直接影响 Release 的记录，且此字段必须
description：描述


charts

charts 文件夹是用于存放依赖的 chart 的。当有依赖需要管理时，可添加 requirements.yaml 文件，可用于管理项目内或者外部的依赖。

.helmignore

.helmignore 类似于 .gitignore 和 .dockerignore 之类的，用于忽略掉一些不想包含在 chart 内的文件。

templates

templates 文件夹内存放着 chart 所使用的模板文件，也是 chart 的实际执行内容。在使用 chart 进行安装的时候，会将 下面介绍的 values.yaml 中的配置项与 templates 中的模板进行组装，生成最终要执行的配置文件。

templates 中，推荐命名应该清晰，如 xx-deployment.yaml，中间使用 - 进行分割，避免使用驼峰式命名。

Notes.txt 文件在 helm install 完成后，会进行回显，可用于解释说明如何访问服务等。

values.yaml

values.yaml 存放着项目的一些可配置项，如镜像的名称或者 tag 之类的。作用就是用于和模板进行组装。

编写 chart

了解完结构之后，我们来实际编写我们的 chart 。所有完整的代码可在 SayThx 项目 获取。

# Chart.yaml
apiVersion: v1
appVersion: "1.0"
description: A Helm chart for SayThx.
name: saythx
version: 0.1.0
maintainers:
  - name: Jintao Zhang


可添加 maintainers 字段，表示维护者。

# values.yaml

# backend is the values for backend
backend:
  image: taobeier/saythx-be
  tag: "1.0"
  pullPolicy: IfNotPresent
  replicas: 1

# namespace is the values for deploy namespace
namespace: work

# service.type is the values for service type
service:
  type: NodePort


values.yaml 文件中定义了我们预期哪些东西是可配置的，比如 namespace 以及镜像名称 tag 等。这里只是贴出了部分内容，仅做说明使用，完整内容可查看我们的示例项目 。

写 values.yaml 文件的时候，由于是使用 YAML 格式的配置，所以它非常的灵活，即可以使用如上面例子中的 backend 那种字典类型的， 也可以写成简单的 k-v 形式。但通常来讲，应该尽可能的将它写的清晰明确。并且容易被替换。

# templates/backend-service.yaml 

apiVersion: v1
kind: Service
metadata:
  labels:
    app: backend
  name: saythx-backend
  namespace: {{ .Values.namespace }}
spec:
  ports:
  - protocol: TCP
    port: 8080
    targetPort: 8080
  selector:
    app: backend
  type: {{ .Values.service.type }}


将我们之前写的部署文件模板化，与配置项进行组装。

1. Get the application URL by running these commands:
{{- if contains "NodePort" .Values.service.type }}
  export NODE_PORT=$(kubectl get --namespace {{ .Values.namespace }} -o jsonpath="{.spec.ports[0].nodePort}" services saythx-frontend)
  export NODE_IP=$(kubectl get nodes --namespace {{ .Values.namespace }} -o jsonpath="{.items[0].status.addresses[0].address}")
  echo http://$NODE_IP:$NODE_PORT
{{- else if contains "ClusterIP" .Values.service.type }}
  export POD_NAME=$(kubectl get pods --namespace {{ .Values.namespace }} -l "app=frontend" -o jsonpath="{.items[0].metadata.name}")
  echo "Visit http://127.0.0.1:8080 to use your application"
  kubectl --namespace {{ .Values.namespace }} port-forward $POD_NAME 8080:80
{{- end }}


上面这是 NOTES.txt 文件内的内容。 这些内容会在 helm install 执行成功后显示在终端，用于说明服务如何访问或者其他注意事项等。

当然，这里的内容主要是为了说明如何编写 chart ，在实践中，尽量避免硬编码配置在里面。

部署

直接部署

Helm 的 chart 可以直接在源码目录下通过 helm install 完成部署。例如：

➜  saythx helm install saythx
NAME:   handy-seastar
LAST DEPLOYED: Tue Nov 20 23:33:42 2018
NAMESPACE: default
STATUS: DEPLOYED

RESOURCES:
==> v1/Namespace
NAME  STATUS  AGE
work  Active  1s

==> v1/Service
NAME             TYPE      CLUSTER-IP      EXTERNAL-IP  PORT(S)         AGE
saythx-backend   NodePort  10.102.206.213  <none>       8080:30663/TCP  0s
saythx-frontend  NodePort  10.96.109.45    <none>       80:30300/TCP    0s
saythx-redis     NodePort  10.97.174.8     <none>       6379:30589/TCP  0s

==> v1/Deployment
NAME             DESIRED  CURRENT  UP-TO-DATE  AVAILABLE  AGE
saythx-backend   1        1        1           0          0s
saythx-frontend  1        1        1           0          0s
saythx-redis     1        1        1           0          0s
saythx-work      1        1        1           0          0s

==> v1/Pod(related)
NAME                              READY  STATUS             RESTARTS  AGE
saythx-backend-7f6d86d9c8-xqttg   0/1    ContainerCreating  0         0s
saythx-frontend-777fc64997-9zmq6  0/1    Pending            0         0s
saythx-redis-8558c7d7d-lh5df      0/1    ContainerCreating  0         0s
saythx-work-9b4446d84-c2pr4       0/1    ContainerCreating  0         0s

NOTES:
1. Get the application URL by running these commands:
  export NODE_PORT=$(kubectl get --namespace work -o jsonpath="{.spec.ports[0].nodePort}" services saythx-frontend)
  export NODE_IP=$(kubectl get nodes --namespace work -o jsonpath="{.items[0].status.addresses[0].address}")
  echo http://$NODE_IP:$NODE_PORT


打包

当然，我们也可以将 chart 打包，以便于分发。

➜  saythx helm package saythx 
Successfully packaged chart and saved it to: /root/saythx/saythx-0.1.0.tgz


可以看到打包时是按照 chart 的名字加版本号进行命名的。

至于部署，和前面没什么太大区别， helm install saythx-0.1.0.tgz 即可。

访问服务

前面在部署完成后，有一些返回信息，我们来按照其内容访问我们的服务：

➜  saythx export NODE_PORT=$(kubectl get --namespace work -o jsonpath="{.spec.ports[0].nodePort}" services saythx-frontend)
➜  saythx export NODE_IP=$(kubectl get nodes --namespace work -o jsonpath="{.items[0].status.addresses[0].address}")
➜  saythx echo http://$NODE_IP:$NODE_PORT
http://172.17.0.5:30300
➜  saythx curl http://172.17.0.5:30300
<!DOCTYPE html><html lang=en><head><meta charset=utf-8><meta http-equiv=X-UA-Compatible content="IE=edge"><meta name=viewport content="width=device-width,initial-scale=1"><link rel=icon href=/favicon.ico><title>fe</title><link href=/css/app.0a6f0b04.css rel=preload as=style><link href=/css/chunk-vendors.ea3fa8e3.css rel=preload as=style><link href=/js/app.ee469174.js rel=preload as=script><link href=/js/chunk-vendors.14b9b088.js rel=preload as=script><link href=/css/chunk-vendors.ea3fa8e3.css rel=stylesheet><link href=/css/app.0a6f0b04.css rel=stylesheet></head><body><noscript><strong>We're sorry but fe doesn't work properly without JavaScript enabled. Please enable it to continue.</strong></noscript><div id=app></div><script src=/js/chunk-vendors.14b9b088.js></script><script src=/js/app.ee469174.js></script></body></html>


服务可以正常访问。

总结

通过本节我们学习到了 chart 的实际结构，及编写方式。以及编写了我们自己的 chart 并使用该 chart 部署了服务。

示例项目还仅仅是个小项目，试想当我们需要部署一个大型项目，如果不通过类似 Helm 这样的软件进行管理，每次的更新发布，维护 YAML 的配置文件就会很繁琐了。

另外，Helm 的功能还不仅限于此，使用 Helm 我们还可以管理 Release ，并进行更新回滚等操作。以及，我们可以搭建自己的私有 chart 仓库等。

下节开始，我们将进入深入学习阶段，逐个讲解 K8S 的核心组件，以便后续遇到问题时，可快速定位和解决。

                        
                        
                            