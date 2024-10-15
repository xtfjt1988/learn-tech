
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            13 理解对方暴露服务的对象 Ingress 和 Service
                            Kubernetes 中的服务（Service）可以理解为对外暴露服务的最小单元对象，这个和 Pod 对象还是有不同的。例如用户通过发布服务对象 Deployment 发布应用，当在容器集群中启动后，ReplicaSet 副本对象会帮我们维持 Pod 实例的副本数。Pod 使用的容器网络默认会选择构建在主机网络上的覆盖网络（Overlay），默认外网是无法直接访问这些 Pod 实例服务的。为了能有效对接容器网络，Kubernetes 创建了另外一层虚拟网络 ClusterIP，即 Service 对象。从实现上来看，它借助 iptables 调用底层 netfilter 实现了虚拟 IP，然后通过相应的规则链把南北向流量准确无误的接入后端 Pod 实例。随着需求的衍生，后来扩展的 Ingress 对象则是借助第三方代理服务如 HAProxy、Nginx 等 7 层引流工具打通外部流量和内部 Service 对象的通路。Ingress 对象的目的就是为了解决容器集群中需要高性能应用网关接入的需求。

Service 的思考

Service 定义的网络基于 iptables 编排 netfilter 规则来支持虚拟 IP。Service 对象被设计为反向代理模式，支持南北向流量的负载均衡，通过 DNAT 把流量转到后端的具体业务的 Pod 中。为了劫持接入流量和 NAT 转换，Kubernetes 创建了两条自定义链规则 PREROUTING 和 OUTPUT。如：

-A PREROUTING -m comment --comment "kubernetes service portals" -j KUBE-SERVICES
...
-A OUTPUT -m comment --comment "kubernetes service portals" -j KUBE-SERVICES
...



PREROUTING 主要处理从外部引入的流量和来自 Pod 容器网络的引入流量，OUTPUT 主要处理流出到外部网络的流量和流出到 Pod 容器网络的流量。

因为发布的服务肯定需要对外暴露服务，所以 Kubernetes 创建了一个自定义规则链 KUBE-SERVICE 来支持集群级别的服务发现，即 ClusterIP 和 LoadBalancer 类型，最后通过另外一条自定义规则链 KUBE-NODEPORTS 来对外暴露服务，案例如下：

-A KUBE-SERVICES -m comment --comment "kubernetes service nodeports; NOTE: this must be the last rule in this chain" -m addrtype --dst-type LOCAL -j KUBE-NODEPORTS



每一个 Service 都会创建一套规则链，NODEPORTS 规则必须在最后一行。因此不难知道，当服务数量达到上万个时候，iptables 是无法承载这种规模的规则链的处理的。所以，在最新服务方案中默认引入 ipvs 取代 iptables 的原因。

ClusterIP 类型

Service 默认类型，配合场景可以分为以下 5 种分类：


ClusterIP service
ClusterIP service with session affinity
ClusterIP with external IPs
ClusterIP service without any endpoints
Headless service


为了加深印象，以下通过案例来学习 Service 对象：

#redis.yaml
apiVersion: apps/v1beta1
kind: Deployment
metadata:
  name: redis
spec:
  replicas: 2
  template:
    metadata:
      labels:
        app: redis
    spec:
      containers:
      - name: redis
        image: redis
        ports:
        - containerPort: 6379
          name: redis



先创建普通的 Service：

#redis-clusterip.yaml
apiVersion: v1
kind: Service
metadata:
  name: redis
spec:
  ports:
  - port: 6379
  selector:
    app: redis



查看 Service 情况：

#kubectl get service redis
NAME    TYPE        CLUSTER-IP   EXTERNAL-IP   PORT(S)    AGE
redis   ClusterIP   10.0.19.85   <none>        6379/TCP   3d4h

#kubectl get endpoints redis
NAME    ENDPOINTS                           AGE
redis   10.244.1.69:6379,10.244.1.70:6379   3d4h



很多用户在遇到这个 Cluster ip 后，就会尝试 ping 它，但是 ping 不通，也不清楚为什么。其实它是一个虚拟 IP，并没有相关网络进程和它关联，当然也就无法访问。Kubernetes 默认会在创建 Service 的时候把此虚拟 IP 加入到内置的 DNS 中用来支持服务发现，仅此而已。如下：

#nslookup redis.default.svc.cluster.local 10.0.0.10
Server:        10.0.0.10
Address:    10.0.0.10#53

Name:    redis.default.svc.cluster.local
Address: 10.0.19.85



现在查看 kube-proxy 通过 iptables 定义的规则链，了解流量接入的实现方法如下：

-A KUBE-SERVICES ! -s 10.244.0.0/16 -d 10.0.19.85/32 -p tcp -m comment --comment "default/redis: cluster IP" -m tcp --dport 6379 -j KUBE-MARK-MASQ
-A KUBE-SERVICES -d 10.0.19.85/32 -p tcp -m comment --comment "default/redis: cluster IP" -m tcp --dport 6379 -j KUBE-SVC-SCFPZ36VFLUNBB47

-A KUBE-SVC-SCFPZ36VFLUNBB47 -m statistic --mode random --probability 0.50000000000 -j KUBE-SEP-UH5EYFQKYB24RWKN
-A KUBE-SVC-SCFPZ36VFLUNBB47 -j KUBE-SEP-5MXPM55VLN7O52FQ

-A KUBE-SEP-UH5EYFQKYB24RWKN -s 10.244.1.69/32 -j KUBE-MARK-MASQ
-A KUBE-SEP-UH5EYFQKYB24RWKN -p tcp -m tcp -j DNAT --to-destination 10.244.1.69:6379

-A KUBE-SEP-5MXPM55VLN7O52FQ -s 10.244.1.70/32 -j KUBE-MARK-MASQ
-A KUBE-SEP-5MXPM55VLN7O52FQ -p tcp -m tcp -j DNAT --to-destination 10.244.1.70:6379



注意，Service 这层的负载均衡是通过 iptables 的 statistic 模块实现：

-A KUBE-SVC-SCFPZ36VFLUNBB47 -m statistic --mode random --probability 0.50000000000 -j KUBE-SEP-UH5EYFQKYB24RWKN
-A KUBE-SVC-SCFPZ36VFLUNBB47 -j KUBE-SEP-5MXPM55VLN7O52FQ



还有一个问题，就是 Pod 内网 IP 访问 Service IP 的时候是会发生端口流量回流的。如何让端口流量不回流的技术，专业术语叫 hairpin NAT。通过 kubelet 配置参数 --hairpin-mode=hairpin-veth 可以让 Pod 内网网卡自动支持 hairpin，从而解决虚拟网卡流量回流的问题。

让 ClusterIP 支持流量亲和性，你需要如下声明对象：

#redis-clusterip-sa.yaml
apiVersion: v1
kind: Service
metadata:
  name: redis-sa
spec:
  sessionAffinity: ClientIP
  ports:
  - port: 6379
  selector:
    app: redis



查看 iptables 生成的规则如下：

-A KUBE-SERVICES ! -s 10.244.0.0/16 -d 10.0.219.234/32 -p tcp -m comment --comment "default/redis-sa: cluster IP" -m tcp --dport 6379 -j KUBE-MARK-MASQ
-A KUBE-SERVICES -d 10.0.219.234/32 -p tcp -m comment --comment "default/redis-sa: cluster IP" -m tcp --dport 6379 -j KUBE-SVC-YUZPDSCUOF7FG5LD

-A KUBE-SVC-YUZPDSCUOF7FG5LD -m recent --rcheck --seconds 10800 --reap --name KUBE-SEP-6MUUJB4K75LGZXHS --mask 255.255.255.255 --rsource -j KUBE-SEP-6MUUJB4K75LGZXHS
-A KUBE-SVC-YUZPDSCUOF7FG5LD -m recent --rcheck --seconds 10800 --reap --name KUBE-SEP-F5DCISRHJOTG66JA --mask 255.255.255.255 --rsource -j KUBE-SEP-F5DCISRHJOTG66JA
-A KUBE-SVC-YUZPDSCUOF7FG5LD -m statistic --mode random --probability 0.50000000000 -j KUBE-SEP-6MUUJB4K75LGZXHS
-A KUBE-SVC-YUZPDSCUOF7FG5LD -j KUBE-SEP-F5DCISRHJOTG66JA

-A KUBE-SEP-6MUUJB4K75LGZXHS -s 10.244.1.69/32 -j KUBE-MARK-MASQ
-A KUBE-SEP-6MUUJB4K75LGZXHS -p tcp -m recent --set --name KUBE-SEP-6MUUJB4K75LGZXHS --mask 255.255.255.255 --rsource -m tcp -j DNAT --to-destination 10.244.1.69:6379

-A KUBE-SEP-F5DCISRHJOTG66JA -s 10.244.1.70/32 -j KUBE-MARK-MASQ
-A KUBE-SEP-F5DCISRHJOTG66JA -p tcp -m recent --set --name KUBE-SEP-F5DCISRHJOTG66JA --mask 255.255.255.255 --rsource -m tcp -j DNAT --to-destination 10.244.1.70:6379



通过以上的规则链，可以知道链路亲和性主要是通过 iptables 的 recent 模块来支持的。

如果不想创建 ClusterIP，可以声明 None 去掉 ClusterIP 支持，如下：

#redis-clusterip-headless.yaml
apiVersion: v1
kind: Service
metadata:
  name: redis-headless
spec:
  clusterIP: None
  ports:
  - port: 6379
  selector:
    app: redis



通过内网 DNS 可以了解到，查询 Service 将直接列出 Pod 的 IP 了，如下：

#nslookup redis-headless.default.svc.cluster.local 10.0.0.10
Server:        10.0.0.10
Address:    10.0.0.10#53

Name:    redis-headless.default.svc.cluster.local
Address: 10.244.1.69
Name:    redis-headless.default.svc.cluster.local
Address: 10.244.1.70



NodePort 类型

NodePort 类型也是我们最常用的类型，按照场景分类如下 5 种：


NodePort service
NodePort service with externalTrafficPolicy: Local
NodePort service without any endpoints
NodePort service with session affinity
NodePort service with externalTrafficPolicy: Local and session affinity


一般常见的定义如下：

#redis-nodeport.yaml
apiVersion: v1
kind: Service
metadata:
  name: redis-nodeport
spec:
  type: NodePort
  ports:
  - nodePort: 30001
    port: 6379
    targetPort: 6379    
  selector:
    app: redis



查看创建结果如下：

#kubectl get service redis-nodeport
NAME             TYPE       CLUSTER-IP     EXTERNAL-IP   PORT(S)          AGE
redis-nodeport   NodePort   10.0.118.143   <none>        6379:30001/TCP   107s

#kubectl get endpoints redis-nodeport
NAME             ENDPOINTS          AGE
redis-nodeport   10.244.0.4:6379   110s



通过暴露在主机层面的 30001 端口，外网可以轻松访问到容器集群中的服务。

Ingress 的思考

Ingress 打通了从集群外部到集群内服务的 HTTP 和 HTTPS 路由。流量路由由 Ingress 资源上定义的规则控制。其实真正的流量负载由第三方代理服务来支撑，如 HAProxy。大家可以回顾一下，在没有 Ingress 之前，我们一般都会在集群外部部署接入网关，然后把流量引入集群。但是 Kubernetes 集群中的服务是动态的，如何能通过查询 APIServer 动态获得服务列表和端口，然后实时更新到网关中，这不就完美实现业务需求了吗？是的，Ingress 因此而生，它的主要能力就是为服务提供外部可访问的 URL、负载均衡流量、终止 SSL/TLS。

通过一个最小的 Ingress 资源示例来熟悉下：

apiVersion: networking.k8s.io/v1beta1
kind: Ingress
metadata:
  name: test-ingress
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
spec:
  rules:
  - http:
      paths:
      - path: /testpath
        pathType: Prefix
        backend:
          serviceName: test
          servicePort: 80



Nginx 的规则更新主要是通过 nginx-controller 定期从 APIServer 中抓取获得。

特性一：服务分组

一个分组配置根据请求的 HTTP URI 将流量从单个 IP 地址路由到多个服务。Ingress 允许将负载均衡器的数量降至最低。例如，这样的设置：

foo.bar.com -> 178.91.123.132 -> / foo    service1:4200
                                 / bar    service2:8080



特性二：基于名称的虚拟托管

基于名称的虚拟域名支持将针对多个主机名的 HTTP 流量路由到同一 IP 地址上。

foo.bar.com --|                 |-> foo.bar.com service1:80
              | 178.91.123.132  |
bar.foo.com --|                 |-> bar.foo.com service2:80



特性三：TLS 终止

通过设定包含 TLS 私钥和证书的 Secret 来保护 Ingress。目前，Ingress 只支持单个 TLS 端口 443，并假定 TLS 终止。

apiVersion: v1
kind: Secret
metadata:
  name: testsecret-tls
  namespace: default
data:
  tls.crt: base64 encoded cert
  tls.key: base64 encoded key
type: kubernetes.io/tls



在 Ingress 中引用此 Secret 将会告诉 Ingress 控制器使用 TLS 加密从客户端到负载均衡器的通道。你需要确保创建的 TLS Secret 来自包含 sslexample.foo.com 的公用名称（CN）的证书。这里的公共名称也被称为全限定域名（FQDN）。

apiVersion: networking.k8s.io/v1beta1
kind: Ingress
metadata:
  name: tls-example-ingress
spec:
  tls:
  - hosts:
    - sslexample.foo.com
    secretName: testsecret-tls
  rules:
    - host: sslexample.foo.com
      http:
        paths:
        - path: /
          backend:
            serviceName: service1
            servicePort: 80



从案例中来看，Ingress 虽然承担这应用网关的职责，但是其设计的能力受制于第三方代理组件，反而没有自定义应用网关那么灵活。所以在具体业务中，我们仍然需要考量需求后在觉得是否需要引入 Ingress。

总结

集群对外服务对象 Service 和 Ingress 往往被人误解，并和 Pod 服务发现混在一起。通过以上的案例分析，我们可以充分理解 Service 的实现。从实践中发现，Service 这层的作用是起到承上启下的入口作用，功能上只要能暴露主机端口 NodePort 即可。采用 iptables 实现的 NAT 转换只有在上万规模服务的时候，规则链的暴增才会影响性能，采用 ipvs 反向代理模块后可以缓解。但是 iptables 定义的规则链还要解决 Service 和 Pod 容器网络的 NAT 连通，目前还无法完全去掉 iptables 模块。随着 eBPF 的兴起，预计后面去 iptables 化指日可待。

                        
                        
                            