
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            17 庖丁解牛：kube-proxy
                            整体概览

在第 3 节中，我们了解到 kube-proxy 的存在，而在第 7 中，我们学习到了如何将运行于 K8S 中的服务以 Service 的方式暴露出来，以供访问。

本节，我们来介绍下 kube-proxy 了解下它是如何支撑起这种类似服务发现和代理相关功能的。

kube-proxy 是什么

kube-proxy 是 K8S 运行于每个 Node 上的网络代理组件，提供了 TCP 和 UDP 的连接转发支持。

我们已经知道，当 Pod 在创建和销毁的过程中，IP 可能会发生变化，而这就容易造成对其有依赖的服务的异常，所以通常情况下，我们都会使用 Service 将后端 Pod 暴露出来，而 Service 则较为稳定。

还是以我们之前的 SayThx 项目为例，但我们只部署其中没有任何依赖的后端资源 Redis 。

master $ git clone https://github.com/tao12345666333/saythx.git
Cloning into 'saythx'...
remote: Enumerating objects: 110, done.
remote: Counting objects: 100% (110/110), done.
remote: Compressing objects: 100% (82/82), done.
remote: Total 110 (delta 27), reused 102 (delta 20), pack-reused 0
Receiving objects: 100% (110/110), 119.42 KiB | 0 bytes/s, done.
Resolving deltas: 100% (27/27), done.
Checking connectivity... done.
master $ cd saythx/deploy
master $ ls
backend-deployment.yaml  frontend-deployment.yaml  namespace.yaml         redis-service.yaml
backend-service.yaml     frontend-service.yaml     redis-deployment.yaml  work-deployment.yaml


进入配置文件所在目录后，开始创建相关资源：

master $ kubectl apply -f namespace.yaml
namespace/work created
master $ kubectl apply -f redis-deployment.yaml
deployment.apps/saythx-redis created
master $ kubectl  apply -f redis-service.yaml
service/saythx-redis created
master $ kubectl -n work get all
NAME                               READY     STATUS    RESTARTS   AGE
pod/saythx-redis-8558c7d7d-wsn2w   1/1       Running   0          21s

NAME                   TYPE       CLUSTER-IP       EXTERNAL-IP   PORT(S)          AGE
service/saythx-redis   NodePort   10.103.193.175   <none>        6379:31269/TCP   6s

NAME                           DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/saythx-redis   1         1         1            1           21s

NAME                                     DESIRED   CURRENT   READY     AGE
replicaset.apps/saythx-redis-8558c7d7d   1         1         1         21s


可以看到 Redis 正在运行，并通过 NodePort 类型的 Service 暴露出来，我们访问来确认下。

master $ docker run --rm -it --network host redis:alpine redis-cli -p 31269
Unable to find image 'redis:alpine' locally
alpine: Pulling from library/redis
4fe2ade4980c: Already exists
fb758dc2e038: Pull complete
989f7b0c858b: Pull complete
8dd99d530347: Pull complete
7137334fa8f0: Pull complete
30610ca64487: Pull complete
Digest: sha256:8fd83c5986f444f1a5521e3eda7395f0f21ff16d33cc3b89d19ca7c58293c5dd
Status: Downloaded newer image for redis:alpine
127.0.0.1:31269> set name kubernetes
OK
127.0.0.1:31269> get name 
"kubernetes"


可以看到已经可以正常访问。接下来，我们来看下 31269 这个端口的状态。

master $ netstat  -ntlp |grep 31269
tcp6       0      0 :::31269                :::*                    LISTEN      2716/kube-proxy


可以看到该端口是由 kube-proxy 所占用的。

接下来，查看当前集群的 Service 和 Endpoint

master $ kubectl -n work get svc
NAME           TYPE       CLUSTER-IP       EXTERNAL-IP   PORT(S)          AGE
saythx-redis   NodePort   10.103.193.175   <none>        6379:31269/TCP   10m
master $ kubectl -n work get endpoints
NAME           ENDPOINTS        AGE
saythx-redis   10.32.0.2:6379   10m
master $ kubectl -n work get pod -o wide
NAME                           READY     STATUS    RESTARTS   AGE       IP          NODE      NOMINATED NODE
saythx-redis-8558c7d7d-wsn2w   1/1       Running   0          12m       10.32.0.2   node01    <none>


可以很直观的看到 Endpoint 当中的便是 Pod 的 IP，现在我们将该服务进行扩容（实际情况下并不会这样处理）。

直接通过 kubectl scale 操作

master $ kubectl  -n work scale --replicas=2 deploy/saythx-redis
deployment.extensions/saythx-redis scaled
master $ kubectl  -n work get all
NAME                               READY     STATUS    RESTARTS   AGE
pod/saythx-redis-8558c7d7d-sslpj   1/1       Running   0          10s
pod/saythx-redis-8558c7d7d-wsn2w   1/1       Running   0          16m

NAME                   TYPE       CLUSTER-IP       EXTERNAL-IP   PORT(S)          AGE
service/saythx-redis   NodePort   10.103.193.175   <none>        6379:31269/TCP   16m

NAME                           DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
deployment.apps/saythx-redis   2         2         2            2           16m


查看 Endpoint 信息：

master $ kubectl -n work get endpoints
NAME           ENDPOINTS                       AGE
saythx-redis   10.32.0.2:6379,10.32.0.3:6379   17m


可以看到 Endpoint 已经自动发生了变化，而这也意味着 Service 代理的后端节点将增加一个。

kube-proxy 如何工作

kube-proxy 在 Linux 系统上当前支持三种模式，可通过 --proxy-mode 配置：


userspace：这是很早期的一种方案，但效率上显著不足，不推荐使用。
iptables：当前的默认模式。比 userspace 要快，但问题是会给机器上产生很多 iptables 规则。
ipvs：为了解决 iptables 的性能问题而引入，采用增量的方式进行更新。


下面我们以 iptables 的模式稍作介绍。

master $ iptables -t nat -L 
Chain PREROUTING (policy ACCEPT)
target     prot opt source               destination
KUBE-SERVICES  all  --  anywhere             anywhere             /* kubernetes service portals */
DOCKER     all  --  anywhere             anywhere             ADDRTYPE match dst-type LOCAL

Chain INPUT (policy ACCEPT)
target     prot opt source               destination

Chain OUTPUT (policy ACCEPT)
target     prot opt source               destination
KUBE-SERVICES  all  --  anywhere             anywhere             /* kubernetes service portals */
DOCKER     all  --  anywhere            !127.0.0.0/8          ADDRTYPE match dst-type LOCAL

Chain POSTROUTING (policy ACCEPT)
target     prot opt source               destination
KUBE-POSTROUTING  all  --  anywhere             anywhere             /* kubernetes postrouting rules */
MASQUERADE  all  --  172.18.0.0/24        anywhere

Chain DOCKER (2 references)
target     prot opt source               destination
RETURN     all  --  anywhere             anywhere

Chain KUBE-MARK-DROP (0 references)
target     prot opt source               destination
MARK       all  --  anywhere             anywhere             MARK or 0x8000

Chain KUBE-MARK-MASQ (7 references)
target     prot opt source               destination
MARK       all  --  anywhere             anywhere             MARK or 0x4000

Chain KUBE-NODEPORTS (1 references)
target     prot opt source               destination
KUBE-MARK-MASQ  tcp  --  anywhere             anywhere             /* work/saythx-redis: */ tcp dpt:31269
KUBE-SVC-SMQNAAUIAENDDGYQ  tcp  --  anywhere             anywhere             /* work/saythx-redis: */ tcp dpt:31269

Chain KUBE-POSTROUTING (1 references)
target     prot opt source               destination
MASQUERADE  all  --  anywhere             anywhere             /* kubernetes service traffic requiring SNAT */ mark match 0x4000/0x4000

Chain KUBE-SEP-2LZPYBS4HUAJKDFL (1 references)
target     prot opt source               destination
KUBE-MARK-MASQ  all  --  10.32.0.2            anywhere             /* kube-system/kube-dns:dns-tcp */
DNAT       tcp  --  anywhere             anywhere             /* kube-system/kube-dns:dns-tcp */ tcp to:10.32.0.2:53

Chain KUBE-SEP-3E4LNQKKWZF7G6SH (1 references)
target     prot opt source               destination
KUBE-MARK-MASQ  all  --  10.32.0.1            anywhere             /* kube-system/kube-dns:dns-tcp */
DNAT       tcp  --  anywhere             anywhere             /* kube-system/kube-dns:dns-tcp */ tcp to:10.32.0.1:53

Chain KUBE-SEP-3IDG7DUGN3QC2UZF (1 references)
target     prot opt source               destination
KUBE-MARK-MASQ  all  --  172.17.0.120         anywhere             /* default/kubernetes:https */
DNAT       tcp  --  anywhere             anywhere             /* default/kubernetes:https */ tcp to:172.17.0.120:6443

Chain KUBE-SEP-JZWS2VPNIEMNMNB2 (1 references)
target     prot opt source               destination
KUBE-MARK-MASQ  all  --  10.32.0.2            anywhere             /* kube-system/kube-dns:dns */
DNAT       udp  --  anywhere             anywhere             /* kube-system/kube-dns:dns */ udp to:10.32.0.2:53

Chain KUBE-SEP-OEY6JJQSBCQPRKHS (1 references)
target     prot opt source               destination
KUBE-MARK-MASQ  all  --  10.32.0.1            anywhere             /* kube-system/kube-dns:dns */
DNAT       udp  --  anywhere             anywhere             /* kube-system/kube-dns:dns */ udp to:10.32.0.1:53

Chain KUBE-SEP-QX7VDAS5KDY6V3EV (1 references)
target     prot opt source               destination
KUBE-MARK-MASQ  all  --  10.32.0.2            anywhere             /* work/saythx-redis: */
DNAT       tcp  --  anywhere             anywhere             /* work/saythx-redis: */ tcp to:10.32.0.2:6379

Chain KUBE-SERVICES (2 references)
target     prot opt source               destination
KUBE-SVC-SMQNAAUIAENDDGYQ  tcp  --  anywhere             10.103.193.175       /* work/saythx-redis: cluster IP */ tcp dpt:6379
KUBE-NODEPORTS  all  --  anywhere             anywhere             /* kubernetes service nodeports; NOTE: this must be the last rule in this chain */ ADDRTYPE match dst-type LOCAL

Chain KUBE-SVC-ERIFXISQEP7F7OF4 (1 references)
target     prot opt source               destination
KUBE-SEP-3E4LNQKKWZF7G6SH  all  --  anywhere             anywhere             /* kube-system/kube-dns:dns-tcp */ statistic mode random probability 0.50000000000
KUBE-SEP-2LZPYBS4HUAJKDFL  all  --  anywhere             anywhere             /* kube-system/kube-dns:dns-tcp */

Chain KUBE-SVC-SMQNAAUIAENDDGYQ (2 references)
target     prot opt source               destination
KUBE-SEP-QX7VDAS5KDY6V3EV  all  --  anywhere             anywhere             /* work/saythx-redis: */


以上输出已经尽量删掉了无关的内容。

当开始访问的时候先要经过 PREROUTING 链，转到 KUBE-SERVICES 链，当查询到匹配的规则之后，请求将转向 KUBE-SVC-SMQNAAUIAENDDGYQ 链，进而到达 KUBE-SEP-QX7VDAS5KDY6V3EV 对应于我们的 Pod。(注：为了简洁，上述 iptables 规则是部署一个 Pod 时的场景)

当搞懂了这些之后，如果你想了解这些 iptables 规则实际又是如何创建和维护的，那可以参考下 proxier 的具体实现，这里不再展开。

总结

本节中我们介绍了 kube-proxy 的主要功能和基本流程，了解到了它对于服务注册发现和代理访问等起到了很大的作用。而它在 Linux 下的代理模式也有 userspace，iptables 和 ipvs 等。

默认情况下我们使用 iptables 的代理模式，当创建新的 Service ，或者 Pod 进行变化时，kube-proxy 便会去维护 iptables 规则，以确保请求可以正确的到达后端服务。

当然，本节中并没有提到 kube-proxy 的 session affinity 相关的特性，如有需要可进行下尝试。

下节，我们将介绍实际运行着容器的 Docker，大致了解下在 K8S 中它所起的作用，及他们之间的交互方式。

                        
                        
                            