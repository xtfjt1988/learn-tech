
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            02 深入理解 Kubernets 的编排对象
                            Kubernetes 系统是一套分布式容器应用编排系统，当我们用它来承载业务负载时主要使用的编排对象有 Deployment、ReplicaSet、StatefulSet、DaemonSet 等。读者可能好奇的地方是 Kubernetes 管理的对象不是 Pod 吗？为什么不去讲讲如何灵活配置 Pod 参数。其实这些对象都是对 Pod 对象的扩展封装。并且这些对象作为核心工作负载 API 固化在 Kubernetes 系统中了。所以 ，我们有必要认真的回顾和理解这些编排对象，依据生产实践的场景需要，合理的配置这些编排对象，让 Kubernetes 系统能更好的支持我们的业务需要。本文会从实际应用发布的场景入手，分析和梳理具体场景中需要考虑的编排因素，并整理出一套可以灵活使用的编排对象使用实践。

常规业务容器部署策略

策略一：强制运行不少于 2 个容器实例副本

在应对常规业务容器的场景之下，Kubernetes 提供了 Deployment 标准编排对象，从命令上我们就可以理解它的作用就是用来部署容器应用的。Deployment 管理的是业务容器 Pod，因为容器技术具备虚拟机的大部分特性，往往让用户误解认为容器就是新一代的虚拟机。从普通用户的印象来看，虚拟机给用户的映象是稳定可靠。如果用户想当然地把业务容器 Pod 也归类为稳定可靠的实例，那就是完全错误的理解了。容器组 Pod 更多的时候是被设计为短生命周期的实例，它无法像虚拟机那样持久地保存进程状态。因为容器组 Pod 实例的脆弱性，每次发布的实例数一定是多副本，默认最少是 2 个。

部署多副本示例：

apiVersion: apps/v1
kind: Deployment
metadata:
  name: nginx-deployment
  labels:
    app: nginx
spec:
  replicas: 2
  selector:
    matchLabels:
      app: nginx
  template:
    metadata:
      labels:
        app: nginx
    spec:
      containers:
      - name: nginx
        image: nginx:1.7.9
        ports:
        - containerPort: 80



策略二：采用节点亲和，Pod 间亲和/反亲和确保 Pod 实现高可用运行

当运维发布多个副本实例的业务容器的时候，一定需要仔细注意到一个事实。Kubernetes 的调度默认策略是选取最空闲的资源主机来部署容器应用，不考虑业务高可用的实际情况。当你的集群中部署的业务越多，你的业务风险会越大。一旦你的业务容器所在的主机出现宕机之后，带来的容器重启动风暴也会即可到来。为了实现业务容错和高可用的场景，我们需要考虑通过 Node 的亲和性和 Pod 的反亲和性来达到合理的部署。这里需要注意的地方是，Kubernetes 的调度系统接口是开放式的，你可以实现自己的业务调度策略来替换默认的调度策略。我们这里的策略是尽量采用 Kubernetes 原生能力来实现。

为了更好地理解高可用的重要性，我们深入探讨一些实际的业务场景。

首先，Kubernetes 并不是谷歌内部使用的 Borg 系统，大部分中小企业使用的 Kubernetes 部署方案都是人工扩展的私有资源池。当你发布容器到集群中，集群不会因为资源不够能自动扩展主机并自动负载部署容器 Pod。即使是在公有云上的 Kubernetes 服务，只有当你选择 Serverlesss Kubernetes 类型时才能实现资源的弹性伸缩。很多传统企业在落地 Kubernetes 技术时比较关心的弹性伸缩能力，目前只能折中满足于在有限静态资源的限制内动态启停容器组 Pod，实现类似的业务容器的弹性。用一个不太恰当的比喻就是房屋中介中，从独立公寓变成了格子间公寓，空间并没有实质性扩大。在实际有限资源的情况下，Kubernetes 提供了打标签的功能，你可以给主机、容器组 Pod 打上各种标签，这些标签的灵活运用，可以帮你快速实现业务的高可用运行。

其次，实践中你会发现，为了高效有效的控制业务容器，你是需要资源主机的。你不能任由 Kubernetes 调度来分配主机启动容器，这个在早期资源充裕的情况下看不到问题。当你的业务复杂之后，你会部署更多的容器到资源池中，这个时间你的业务运行的潜在危机就会出现。因为你没有管理调度资源，导致很多关键业务是运行在同一台服务器上，当主机宕机发生时，让你很难处理这种灾难。所以在实际的业务场景中，业务之间的关系需要梳理清楚，设计单元化主机资源模块，比如 2 台主机为一个单元，部署固定的业务容器组 Pod，并且让容器组 Pod 能足够分散的运行在这两台主机之上，当任何一台主机宕机也不会影响到主体业务，实现真正的高可用。

主机亲和性示例

pods/pod-with-node-affinity.yaml 

apiVersion: v1
kind: Pod
metadata:
  name: with-node-affinity
spec:
  affinity:
    nodeAffinity:
      requiredDuringSchedulingIgnoredDuringExecution:
        nodeSelectorTerms:
        - matchExpressions:
          - key: kubernetes.io/e2e-az-name
            operator: In
            values:
            - e2e-az1
            - e2e-az2
      preferredDuringSchedulingIgnoredDuringExecution:
      - weight: 1
        preference:
          matchExpressions:
          - key: another-node-label-key
            operator: In
            values:
            - another-node-label-value
  containers:
  - name: with-node-affinity
    image: gcr.azk8s.cn/google-samples/node-hello:1.0



目前有两种类型的节点亲和，分别为 requiredDuringSchedulingIgnoredDuringExecution 和 preferredDuringSchedulingIgnoredDuringExecution。你可以视它们为“硬”和“软”，意思是，前者指定了将 pod 调度到一个节点上必须满足的规则，后者指定调度器将尝试执行但不能保证的偏好。

Pod 间亲和与反亲和示例

使用反亲和性避免单点故障例子：

     affinity:
        podAntiAffinity:
          requiredDuringSchedulingIgnoredDuringExecution:
            - labelSelector:
                matchExpressions:
                  - key: "app"
                    operator: In
                    values:
                    - zk
              topologyKey: "kubernetes.io/hostname"



意思是以主机的 hostname 命名空间来调度，运行打了标签键 "app" 并含有 "zk" 值的 Pod 在不同节点上部署。

策略三：使用 preStop Hook 和 readinessProbe 保证服务平滑更新不中断

我们部署应用之后，接下来会做的工作就是服务更新的操作。如何保证容器更新的时候，业务不中断是最重要的关心事项。参考示例：

apiVersion: apps/v1
kind: Deployment
metadata:
  name: nginx
spec:
  replicas: 1
  selector:
    matchLabels:
      component: nginx
  template:
    metadata:
      labels:
        component: nginx
    spec:
      containers:
      - name: nginx
        image: xds2000/nginx-hostname
        ports:
        - name: http
          hostPort: 80
          containerPort: 80
          protocol: TCP
        readinessProbe:
          httpGet:
            path: /
            port: 80
            httpHeaders:
            - name: X-Custom-Header
              value: Awesome
          initialDelaySeconds: 15
          timeoutSeconds: 1
        lifecycle:
          preStop:
            exec:
              command: ["/bin/bash", "-c", "sleep 30"]



给 Pod 里的 container 加 readinessProbe（就绪检查），通常是容器完全启动后监听一个 HTTP 端口，kubelet 发送就绪检查探测包，正常响应说明容器已经就绪，然后修改容器状态为 Ready，当 Pod 中所有容器都 Ready 之后这个 Pod 才会被 Endpoint Controller 加进 Service 对应 Endpoint IP:Port 列表，然后 kube-proxy 再更新节点转发规则，更新完了即便立即有请求被转发到新的 Pod 也能保证能够正常处理连接，避免了连接异常。

给 Pod 里的 container 加 preStop hook，让 Pod 真正销毁前先 sleep 等待一段时间，留点时间给 Endpoint controller 和 kube-proxy 更新 Endpoint 和转发规则，这段时间 Pod 处于 Terminating 状态，即便在转发规则更新完全之前有请求被转发到这个 Terminating 的 Pod，依然可以被正常处理，因为它还在 sleep，没有被真正销毁。

策略四：通过泛域名转发南北向流量范式

常规集群对外暴露一个公网 IP 作为流量入口（可以是 Ingress 或 Service），再通过 DNS 解析配置一个泛域名指向该 IP（比如 *.test.foo.io），现希望根据请求中不同 Host 转发到不同的后端 Service。比如 a.test.foo.io 的请求被转发到 my-svc-a，b.test.foo.io 的请求转发到 my-svc-b。当前 Kubernetes 的 Ingress 并不原生支持这种泛域名转发规则，我们需要借助 Nginx 的 Lua 编程能力解决实现泛域名转发。

Nginx proxy 示例（proxy.yaml）：

apiVersion: apps/v1
kind: Deployment
metadata:
  labels:
    component: nginx
  name: proxy
spec:
  replicas: 1
  selector:
    matchLabels:
      component: nginx
  template:
    metadata:
      labels:
        component: nginx
    spec:
      containers:
      - name: nginx
        image: "openresty/openresty:centos"
        ports:
        - name: http
          containerPort: 80
          protocol: TCP
        volumeMounts:
        - mountPath: /usr/local/openresty/nginx/conf/nginx.conf
          name: config
          subPath: nginx.conf
      - name: dnsmasq
        image: "janeczku/go-dnsmasq:release-1.0.7"
        args:
          - --listen
          - "127.0.0.1:53"
          - --default-resolver
          - --append-search-domains
          - --hostsfile=/etc/hosts
          - --verbose
      volumes:
      - name: config
        configMap:
          name: configmap-nginx

---

apiVersion: v1
kind: ConfigMap
metadata:
  labels:
    component: nginx
  name: configmap-nginx
data:
  nginx.conf: |-
    worker_processes  1;

    error_log  /error.log;

    events {
        accept_mutex on;
        multi_accept on;
        use epoll;
        worker_connections  1024;
    }

    http {
        include       mime.types;
        default_type  application/octet-stream;
        log_format  main  '$time_local $remote_user $remote_addr $host $request_uri $request_method $http_cookie '
                          '$status $body_bytes_sent "$http_referer" '
                          '"$http_user_agent" "$http_x_forwarded_for" '
                          '$request_time $upstream_response_time "$upstream_cache_status"';

        log_format  browser '$time_iso8601 $cookie_km_uid $remote_addr $host $request_uri $request_method '
                          '$status $body_bytes_sent "$http_referer" '
                          '"$http_user_agent" "$http_x_forwarded_for" '
                          '$request_time $upstream_response_time "$upstream_cache_status" $http_x_requested_with $http_x_real_ip $upstream_addr $request_body';

        log_format client '{"@timestamp":"$time_iso8601",'
                          '"time_local":"$time_local",'
                          '"remote_user":"$remote_user",'
                          '"http_x_forwarded_for":"$http_x_forwarded_for",'
                          '"host":"$server_addr",'
                          '"remote_addr":"$remote_addr",'
                          '"http_x_real_ip":"$http_x_real_ip",'
                          '"body_bytes_sent":$body_bytes_sent,'
                          '"request_time":$request_time,'
                          '"status":$status,'
                          '"upstream_response_time":"$upstream_response_time",'
                          '"upstream_response_status":"$upstream_status",'
                          '"request":"$request",'
                          '"http_referer":"$http_referer",'
                          '"http_user_agent":"$http_user_agent"}';

        access_log  /access.log  main;

        sendfile        on;

        keepalive_timeout 120s 100s;
        keepalive_requests 500;
        send_timeout 60000s;
        client_header_buffer_size 4k;
        proxy_ignore_client_abort on;
        proxy_buffers 16 32k;
        proxy_buffer_size 64k;

        proxy_busy_buffers_size 64k;

        proxy_send_timeout 60000;
        proxy_read_timeout 60000;
        proxy_connect_timeout 60000;
        proxy_cache_valid 200 304 2h;
        proxy_cache_valid 500 404 2s;
        proxy_cache_key $host$request_uri$cookie_user;
        proxy_cache_methods GET HEAD POST;

        proxy_redirect off;
        proxy_http_version 1.1;
        proxy_set_header Host                $http_host;
        proxy_set_header X-Real-IP           $remote_addr;
        proxy_set_header X-Forwarded-For     $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto   $scheme;
        proxy_set_header X-Frame-Options     SAMEORIGIN;

        server_tokens off;
        client_max_body_size 50G;
        add_header X-Cache $upstream_cache_status;
        autoindex off;

        resolver      127.0.0.1:53 ipv6=off;

        server {
            listen 80;

            location / {
                set $service  '';
                rewrite_by_lua '
                    local host = ngx.var.host
                    local m = ngx.re.match(host, "(.+).test.foo.io")
                    if m then
                        ngx.var.service = "my-svc-" .. m[1]
                    end
                ';
                proxy_pass http://$service;
            }
        }
    }



用 Service 的示例（service.yaml）：

apiVersion: v1
kind: Service
metadata:
  labels:
    component: nginx
  name: service-nginx
spec:
  type: LoadBalancer
  ports:
  - name: http
    port: 80
    targetPort: http
  selector:
    component: nginx



用 Ingress 的示例（ingress.yaml）：

apiVersion: extensions/v1beta1
kind: Ingress
metadata:
  name: ingress-nginx
spec:
  rules:
  - host: "*.test.foo.io"
    http:
      paths:
      - backend:
          serviceName: service-nginx
          servicePort: 80
        path: /



有状态业务容器部署策略

StatefulSet 旨在与有状态的应用及分布式系统一起使用。为了理解 StatefulSet 的基本特性，我们使用 StatefulSet 部署一个简单的 Web 应用。

创建一个 StatefulSet 示例（web.yaml）：

apiVersion: v1
kind: Service
metadata:
  name: nginx
  labels:
    app: nginx
spec:
  ports:
  - port: 80
    name: web
  clusterIP: None
  selector:
    app: nginx
---
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: web
spec:
  serviceName: "nginx"
  replicas: 2
  selector:
    matchLabels:
      app: nginx
  template:
    metadata:
      labels:
        app: nginx
    spec:
      containers:
      - name: nginx
        image: gcr.azk8s.cn/google_containers/nginx-slim:0.8
        ports:
        - containerPort: 80
          name: web
        volumeMounts:
        - name: www
          mountPath: /usr/share/nginx/html
  volumeClaimTemplates:
  - metadata:
      name: www
    spec:
      accessModes: [ "ReadWriteOnce" ]
      resources:
        requests:
          storage: 1Gi



注意 StatefulSet 对象运行的特点之一就是，StatefulSet 中的 Pod 拥有一个唯一的顺序索引和稳定的网络身份标识。这个输出最终将看起来像如下样子：

kubectl get pods -l app=nginx
NAME      READY     STATUS    RESTARTS   AGE
web-0     1/1       Running   0          1m
web-1     1/1       Running   0          1m



很多文档在提及 Statefulset 对象的概念时，用户容易望文生义，常常把挂盘的容器实例当成有状态实例。这是不准确的解释。在 Kubernetes 的世界里，有稳定的网络身份标识的容器组才是有状态的应用。例如：

for i in 0 1; do kubectl exec web-$i -- sh -c 'hostname'; done
web-0
web-1



另外，我们使用 kubectl run 运行一个提供 nslookup 命令的容器。通过对 Pod 的主机名执行 nslookup，你可以检查他们在集群内部的 DNS 地址。示例如下：

kubectl run -i --tty --image busybox:1.28 dns-test --restart=Never --rm   
nslookup web-0.nginx
Server:    10.0.0.10
Address 1: 10.0.0.10 kube-dns.kube-system.svc.cluster.local

Name:      web-0.nginx
Address 1: 10.244.1.6

nslookup web-1.nginx
Server:    10.0.0.10
Address 1: 10.0.0.10 kube-dns.kube-system.svc.cluster.local

Name:      web-1.nginx
Address 1: 10.244.2.6



策略五：灵活运用 StatefulSet 的 Pod 管理策略

通常对于常规分布式微服务业务系统来说，StatefulSet 的顺序性保证是不必要的。这些系统仅仅要求唯一性和身份标志。为了加快这个部署策略，我们通过引入 .spec.podManagementPolicy 解决。

Parallel pod 管理策略告诉 StatefulSet 控制器并行的终止所有 Pod，在启动或终止另一个 Pod 前，不必等待这些 Pod 变成 Running 和 Ready 或者完全终止状态。示例如下：

apiVersion: v1
kind: Service
metadata:
  name: nginx
  labels:
    app: nginx
spec:
  ports:
  - port: 80
    name: web
  clusterIP: None
  selector:
    app: nginx
---
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: web
spec:
  serviceName: "nginx"
  podManagementPolicy: "Parallel"
  replicas: 2
  selector:
    matchLabels:
      app: nginx
  template:
    metadata:
      labels:
        app: nginx
    spec:
      containers:
      - name: nginx
        image: gcr.azk8s.cn/google_containers/nginx-slim:0.8
        ports:
        - containerPort: 80
          name: web
        volumeMounts:
        - name: www
          mountPath: /usr/share/nginx/html
  volumeClaimTemplates:
  - metadata:
      name: www
    spec:
      accessModes: [ "ReadWriteOnce" ]
      resources:
        requests:
          storage: 1Gi



业务运维类容器部署策略

在我们部署 Kubernetes 扩展 DNS、Ingress、Calico 能力时需要在每个工作节点部署守护进程的程序，这个时候需要采用 DaemonSet 来部署系统业务容器。默认 DaemonSet 采用滚动更新策略来更新容器，可以通过执行如下命令确认：

kubectl get ds/<daemonset-name> -o go-template='{{.spec.updateStrategy.type}}{{"\n"}}'

RollingUpdate



在日常工作中，我们对守护进程只需要执行更换镜像的操作：

kubectl set image ds/<daemonset-name> <container-name>=<container-new-image>



查看滚动更新状态确认当前进度：

kubectl rollout status ds/<daemonset-name>



当滚动更新完成时，输出结果如下：

daemonset "<daemonset-name>" successfully rolled out



此外，我们还有一些定期执行脚本任务的需求，这些需求可以通过 Kubernetes 提供的 CronJob 对象来管理，示例如下：

apiVersion: batch/v1beta1
kind: CronJob
metadata:
  name: hello
spec:
  schedule: "*/1 * * * *"
  successfulJobsHistoryLimit: 0
  failedJobsHistoryLimit: 0
  jobTemplate:
    spec:
      template:
        spec:
          containers:
          - name: hello
            image: busybox
            args:
            - /bin/sh
            - -c
            - date; echo Hello from the Kubernetes cluster
          restartPolicy: OnFailure



总结

本文从实际业务需求出发，带着读者一起梳理了 Kubernetes 的工作负载定义的稳定版本的编排对象 Deployment、StatefulSet、DaemonSet、CronJob。所有提供的资料都是来自行业分享的实践经验的总结，去掉了很多文档的繁琐或者不正确的介绍，帮助读者正确建立合理的编排对象的使用策略。当然，除了这些核心编排对象之外，Kubernetes 还提供了扩展接口，我们通过 Operator 编程框架就可以自定义需要的编排对象，把自己的运维经验用代码规范起来，让你的持续发布的流程更加方便快捷。

                        
                        
                            