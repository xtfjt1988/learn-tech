
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            22 服务增强：Ingress
                            整体概览

通过前面的学习，我们已经知道 K8S 中有 Service 的概念，同时默认情况下还有 CoreDNS 完成集群内部的域名解析等工作，以此完成基础的服务注册发现能力。

在第 7 节中，我们介绍了 Service 的 4 种基础类型，在前面的介绍中，我们一般都在使用 ClusterIP 或 NodePort 等方式将服务暴露在集群内或者集群外。

本节，我们将介绍另一种处理服务访问的方式 Ingress。

Ingress 是什么

通过 kubectl explain ingress 命令，我们来看下对 Ingress 的描述。


Ingress is a collection of rules that allow inbound connections to reach the endpoints defined by a backend. An Ingress can be configured to give services externally-reachable urls, load balance traffic, terminate SSL, offer name based virtual hosting etc.


Ingress 是一组允许外部请求进入集群的路由规则的集合。它可以给 Service 提供集群外部访问的 URL，负载均衡，SSL 终止等。

直白点说，Ingress 就类似起到了智能路由的角色，外部流量到达 Ingress ，再由它按已经制定好的规则分发到不同的后端服务中去。

看起来它很像我们使用的负载均衡器之类的。那你可能会问，Ingress 与 LoadBalancer 类型的 Service 的区别是什么呢？


Ingress 不是一种 Service 类型


Ingress 是 K8S 中的一种资源类型，我们可以直接通过 kubectl get ingress 的方式获取我们已有的 Ingress 资源。


Ingress 可以有多种控制器（实现）


通过之前的介绍，我们知道 K8S 中有很多的 Controller (控制器)，而这些 Controller 已经打包进了 kube-controller-manager 中，通过 --controllers 参数控制启用哪些。

但是 Ingress 的 Controller 并没有包含在其中，而且有多种选择。

由社区维护（或是说官方支持的）有两个：适用于 Google Cloud 的 GLBC，当你使用 GKE 的时候，便会看到它；和 NGINX Ingress Controller 它是使用 ConfigMap 存储 NGINX 配置实现的。

第三方的实现还有：基于 Envoy 的 Contour; F5 的 F5 BIG-IP Controller; 基于 HAProxy 的 haproxy-ingress; 基于 Istio 的 Control Ingress Traffic; 现代化的反向代理服务器 Traefik; 以及 Kong 支持的 Kong Ingress Controller for Kubernetes 和 NGINX 官方支持的 NGINX Ingress Controller。

这里可以看到 K8S 社区和 NGINX 都有 NGINX Ingress Controller，很多人在一开始接触 Ingress 的时候便陷入了选择的苦恼中，除去前面的那些选择外，单 NGINX 的控制器就有两个，到底应该怎么选。

这里提供两点建议：


可能多数人使用的都是 NGINX 而非 NGINX Plus，如果你需要会话保持（Session persistence）的话，那你应该选择 K8S 社区维护的版本
即使我们平时使用 NGINX 的时候，也常常会有动态配置的需求，如果你仍然有这样的需求，那你还是继续使用 K8S 社区维护的版本（其中内置了 Lua 支持）。


如何使用

前面也已经说到了，单纯的创建一个 Ingress 资源没什么意义，我们需要先配置一个 Controller ，才能让它正常工作。国内使用 GKE 的可能不是很多，为了更加通用，这里我们选择 K8S 社区维护的 NGINX Ingress Controller。

安装

整个安装过程其实也比较简单，具体步骤如下（以下步骤中都将直接展示该步骤所需的 YAML 配置文件）：


创建 Namespace


  apiVersion: v1
  kind: Namespace
  metadata:
    name: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx


将以上内容保存为 namespace.yaml 文件，然后执行 kubectl apply -f namespace.yaml 即可。以下步骤均类似，不再赘述。 注意：这里创建 Namespace 只是为了保持集群相对规范，非强制，但推荐此做法。


创建 ConfigMap


  kind: ConfigMap
  apiVersion: v1
  metadata:
    name: nginx-configuration
    namespace: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  
  ---
  kind: ConfigMap
  apiVersion: v1
  metadata:
    name: tcp-services
    namespace: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  
  ---
  kind: ConfigMap
  apiVersion: v1
  metadata:
    name: udp-services
    namespace: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  
  ---


这里创建了几个 ConfigMap，主要是给 Controller 使用。


由于我们的集群使用 kubeadm 创建时，默认开启了 RBAC ，所以这里需要相应的创建对应的 Role 和 RoleBinding。


  apiVersion: v1
  kind: ServiceAccount
  metadata:
    name: nginx-ingress-serviceaccount
    namespace: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  
  ---
  apiVersion: rbac.authorization.k8s.io/v1beta1
  kind: ClusterRole
  metadata:
    name: nginx-ingress-clusterrole
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  rules:
    - apiGroups:
        - ""
      resources:
        - configmaps
        - endpoints
        - nodes
        - pods
        - secrets
      verbs:
        - list
        - watch
    - apiGroups:
        - ""
      resources:
        - nodes
      verbs:
        - get
    - apiGroups:
        - ""
      resources:
        - services
      verbs:
        - get
        - list
        - watch
    - apiGroups:
        - "extensions"
      resources:
        - ingresses
      verbs:
        - get
        - list
        - watch
    - apiGroups:
        - ""
      resources:
        - events
      verbs:
        - create
        - patch
    - apiGroups:
        - "extensions"
      resources:
        - ingresses/status
      verbs:
        - update
  
  ---
  apiVersion: rbac.authorization.k8s.io/v1beta1
  kind: Role
  metadata:
    name: nginx-ingress-role
    namespace: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  rules:
    - apiGroups:
        - ""
      resources:
        - configmaps
        - pods
        - secrets
        - namespaces
      verbs:
        - get
    - apiGroups:
        - ""
      resources:
        - configmaps
      resourceNames:
        - "ingress-controller-leader-nginx"
      verbs:
        - get
        - update
    - apiGroups:
        - ""
      resources:
        - configmaps
      verbs:
        - create
    - apiGroups:
        - ""
      resources:
        - endpoints
      verbs:
        - get
  
  ---
  apiVersion: rbac.authorization.k8s.io/v1beta1
  kind: RoleBinding
  metadata:
    name: nginx-ingress-role-nisa-binding
    namespace: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  roleRef:
    apiGroup: rbac.authorization.k8s.io
    kind: Role
    name: nginx-ingress-role
  subjects:
    - kind: ServiceAccount
      name: nginx-ingress-serviceaccount
      namespace: ingress-nginx
  
  ---
  apiVersion: rbac.authorization.k8s.io/v1beta1
  kind: ClusterRoleBinding
  metadata:
    name: nginx-ingress-clusterrole-nisa-binding
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  roleRef:
    apiGroup: rbac.authorization.k8s.io
    kind: ClusterRole
    name: nginx-ingress-clusterrole
  subjects:
    - kind: ServiceAccount
      name: nginx-ingress-serviceaccount
      namespace: ingress-nginx
  
  ---


关于 RBAC 相关的内容，可查看第 8 节 《安全重点: 认证和授权》，了解此处的配置及其含义。


部署 NGINX Ingress Controller


  apiVersion: extensions/v1beta1
  kind: Deployment
  metadata:
    name: nginx-ingress-controller
    namespace: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  spec:
    replicas: 1
    selector:
      matchLabels:
        app.kubernetes.io/name: ingress-nginx
        app.kubernetes.io/part-of: ingress-nginx
    template:
      metadata:
        labels:
          app.kubernetes.io/name: ingress-nginx
          app.kubernetes.io/part-of: ingress-nginx
        annotations:
          prometheus.io/port: "10254"
          prometheus.io/scrape: "true"
      spec:
        serviceAccountName: nginx-ingress-serviceaccount
        containers:
          - name: nginx-ingress-controller
            image: taobeier/nginx-ingress-controller:0.21.0
            args:
              - /nginx-ingress-controller
              - --configmap=$(POD_NAMESPACE)/nginx-configuration
              - --tcp-services-configmap=$(POD_NAMESPACE)/tcp-services
              - --udp-services-configmap=$(POD_NAMESPACE)/udp-services
              - --publish-service=$(POD_NAMESPACE)/ingress-nginx
              - --annotations-prefix=nginx.ingress.kubernetes.io
            securityContext:
              capabilities:
                drop:
                  - ALL
                add:
                  - NET_BIND_SERVICE
              # www-data -> 33
              runAsUser: 33
            env:
              - name: POD_NAME
                valueFrom:
                  fieldRef:
                    fieldPath: metadata.name
              - name: POD_NAMESPACE
                valueFrom:
                  fieldRef:
                    fieldPath: metadata.namespace
            ports:
              - name: http
                containerPort: 80
              - name: https
                containerPort: 443
            livenessProbe:
              failureThreshold: 3
              httpGet:
                path: /healthz
                port: 10254
                scheme: HTTP
              initialDelaySeconds: 10
              periodSeconds: 10
              successThreshold: 1
              timeoutSeconds: 1
            readinessProbe:
              failureThreshold: 3
              httpGet:
                path: /healthz
                port: 10254
                scheme: HTTP
              periodSeconds: 10
              successThreshold: 1
              timeoutSeconds: 1


注意，这里的镜像是我从官方镜像直接同步的，为了解决国内无法下载镜像的情况。

另外，在启动参数中，指定了我们第二步中创建的 ConfigMap 。以及，在此部署中，用到了之前尚未详细说明的 readinessProbe 和 livenessProbe：我们之前在详解 kubelet 时，大致提到过关于它所具备的职责，这两个配置主要是用于做探针，用户检查 Pod 是否已经准备好接受请求流量和是否存活。

这里还进行了 annotations 里面标注了关于 Prometheus 的相关内容，我们会在下节中描述。

  master $ kubectl -n ingress-nginx get all
  NAME                                            READY     STATUS    RESTARTS   AGE
  pod/nginx-ingress-controller-6f647f7866-659ph   1/1       Running   0          75s
  
  NAME                                       DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
  deployment.apps/nginx-ingress-controller   1         1         1            1           75s
  
  NAME                                                  DESIRED   CURRENT   READY     AGE
  replicaset.apps/nginx-ingress-controller-6f647f7866   1         1         1         75s


可以看到 NGINX Ingress Controller 已经部署成功。


将 NGINX Ingress Controller 暴露至集群外


经过前面的介绍，我们已经知道 Ingress 的作用在于将集群外的请求流量转向集群内的服务，而我们知道，默认情况下集群外和集群内是不互通的，所以必须将 NGINX Ingress Controller 暴露至集群外，以便让其能接受来自集群外的请求。

将其暴露的方式有很多种，这里我们选择我们之前已经介绍过的 NodePort 的方式。选择它主要有以下原因：


我们可以使用纯的 LB 实现完成服务暴露，比如 MetalLB，但它还处于 Beta 阶段，尚未有大规模生产环境使用的验证。
我们可以直接使用宿主机的网络，只需设置 hostNetwork: true 即可，但这个方式可能会带来安全问题。
我们可以选择 External IPs 的方式，但这种方式无法保留请求的源 IP，所以并不是很好。
其实我们一般会选择自己提供边缘节点的方式，不过这种方式是建立在 NodePort 的方式之上，并且需要提供额外的组件，此处就暂不做展开了。


我们使用以下的配置，将 NGINX Ingress Controller 暴露至集群外

  apiVersion: v1
  kind: Service
  metadata:
    name: ingress-nginx
    namespace: ingress-nginx
    labels:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx
  spec:
    type: NodePort
    ports:
      - name: http
        port: 80
        targetPort: 80
        protocol: TCP
      - name: https
        port: 443
        targetPort: 443
        protocol: TCP
    selector:
      app.kubernetes.io/name: ingress-nginx
      app.kubernetes.io/part-of: ingress-nginx


创建该 Service。

  master $ kubectl -n ingress-nginx get svc                                                  
  NAME            TYPE       CLUSTER-IP   EXTERNAL-IP   PORT(S)                      AGE
  ingress-nginx   NodePort   10.0.38.53   <none>        80:30871/TCP,443:30356/TCP   11s


现在，我们直接访问 Node:Port 便可访问到该 Controller。

  master $ curl 172.17.0.3:30871                    
  <html>
  <head><title>404 Not Found</title></head>
  <body>
  <center><h1>404 Not Found</h1></center>
  <hr><center>nginx/1.15.6</center>
  </body>
  </html>


由于我们并没有设置任何的默认响应后端，所以当直接请求时，便返回 404 。

实践

将我们的示例项目 SayThx 通过 Ingress 的方式进行访问。

该示例项目的部署，不再进行赘述。可在 ingress 分支 查看此处所需配置。

在我们将 NGINX Ingress Controller 及 SayThx 项目部署好之后，我们使用以下的配置创建 Ingress 资源。

apiVersion: extensions/v1beta1
kind: Ingress
metadata:
  name: saythx-ing
  namespace: work
  annotations:
    nginx.ingress.kubernetes.io/ssl-redirect: "false"
spec:
  rules:
  - host: saythx.moelove.info
    http:
      paths:
      - path: /
        backend:
          serviceName: saythx-frontend
          servicePort: 80



创建


  master $ kubectl apply -f ingress.yaml         
  ingress.extensions/saythx-ing created
  master $ kubectl -n work get ing
  NAME         HOSTS                 ADDRESS   PORTS     AGE
  saythx-ing   saythx.moelove.info             80        23s



验证


这里来解释下刚才的配置文件。首先，指定了 host: saythx.moelove.info 表示我们想要以 saythx.moelove.info 这个域名来访问它。path 直接写 / 表示所有的请求都转发至名为 saythx-frontend 的服务。

与我们平时使用 NGINX 基本一致。 现在编辑本地的 HOSTS 文件绑定 Node 的IP 与 saythx.moelove.info 这个域名。使用浏览器进行访问 saythx.moelove.info:刚才 Controller 使用 NodePort 暴露服务时的端口：



可以看到已经成功访问。


总结

在本节中，我们介绍了 Ingress 的基本情况，了解了它是 K8S 中的一种资源对象，主要负责将集群外部流量与集群内服务的通信。但它的正常工作离不开 Ingress Controller ，当前官方团队维护的主要有两个 GLBC 和 NGINX Ingress Controller。

我们大致介绍了现有的 Controller 实现，也实践了如何部署 NGINX Ingress Controller 以及如何使用 Ingress 将我们的示例项目暴露至集群外。

NGINX Ingress Controller 的使用，比较符合我们平时使用 NGINX 的习惯，相对来说也比较灵活，后续可看实际情况再进行更多的实践。

至此，K8S 集群的管理，相关原理以及服务的部署等内容就基本介绍完毕。下节，我们将介绍生产实践中至关重要的一环 监控 相关的实践。

                        
                        
                            