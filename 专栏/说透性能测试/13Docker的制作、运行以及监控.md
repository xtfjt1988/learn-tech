
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            13  Docker 的制作、运行以及监控
                            模块三主要讲解了不同层级的监控以及监控的方式，作为模块三的最后一讲，我将带你来学习 Docker 的制作、运行以及监控。对于很多测试来说，经常听到 Docker 容器，但自己好像又不是很熟悉，只是用相关命令去查询日志等，而对于为什么要使用 Docker 还不是特别清楚。其实 Docker 并不难学，有时候你只是差一个学习的切入点，这一讲我会从测试的使用层面带你学习下 Docker 的要点知识，希望作为一名测试的你，对 Docker 也不会再陌生。

为什么要使用 Docker？

你可以回忆下 Docker 的图标（如图 1 所示），是不是像一条船上装了很多集装箱，其实这和Docker 的设计思想有关系，集装箱能解决什么问题呢？就是货物的隔离，如果我们把食物和化学品分别放在两个集装箱中用一艘轮船运走则无妨，但是你不可以把它们放在同一个集装箱中，其实对于 Docker 设计也是如此。

操作系统就相当于这艘轮船，上面可以有很多集装箱，即 Docker，你可以把 Docker 看作是独立的子环境，有独立的系统和应用，比如经常因为一些历史原因开发的多个模块依赖于不同的 JDK 版本，将这两个模块部署在一台 Linux 服务器上可能很容易出问题，但是如果以 Docker 的方式便很容易解决版本冲突的问题。



图 1：Docker 图标

Docker 的用法（基于 CentOS 7.0）

如何学习 Docker 呢？从应用技术维度来看它是一个容器，从学习角度来看它就是一种工具。

对于工具的学习我认为从实际的例子切入是最有代入感的，接下来我就在 CentOS 环境下安装一个基于 Ubuntu 的 Docker 环境，带你从使用层面了解下 Docker，知道 Docker 最基本的安装方式，如下所示：

yum install -y docker  //安装Docker

service docker status //查看Docker运行状态


接下来运行一个 Docker 容器，我目前用的是 CentOS 系统，可现在还需要一个 Ubuntu 环境，我就需要通过如下命令基于 Ubuntu 镜像启动一个容器：

docker run -i -t ubuntu /bin/bash


通过这个命令，就直接创建了基于 Ubuntu 的 Docker 环境，并直接进入了交互 shell，这样你就可以认为是在 Ubuntu 系统下工作了，通过如下命令可以查看版本号：

root@ac3874a96890:/# cat /etc/issue

Ubuntu 20.04.1 LTS


同样的道理，如果你的 Java 服务有的依赖 JDK1.7，有的依赖 JDK1.8，则可以通过 Docker 来做不一样的服务。

上面就是一个简单的实例，在 CentOS 系统里创建一个基于 Docker 的 Ubuntu 系统以实现你特定的需求。

我们再来看看 Docker 常用的命令有哪些，这些可能是你和 Docker 打交道的过程中最常见的命令。

对于 Docker 的命令，都是在 Linux 终端直接输出就可以，比如查看 Docker 镜像，就是直接输出 docker images，展示信息如下所示：

[root@JD ~]# docker images

REPOSITORY                     TAG          IMAGE ID    CREATED             SIZE

docker.io/ubuntu              latest        f643c72bc252

3 weeks ago        72.9 MB

docker.io/gitlab/gitlab-ce    latest        6e2336419031

8 months ago       1.92 GB



REPOSITORY 是指仓库名字；
TAG 一般指版本号；
IMAGE ID 是指镜像 ID；
CREATED 指镜像创建时间；
SIZE 指镜像大小；


如果我们要查看正在运行的 Docker 进程，可以使用命令 docker ps，如下所示：

[root@JD ~]# docker ps

CONTAINER ID        IMAGE                             COMMAND                          CREATED             STATUS                          PORTS                     NAMES

3e6ab93074c7      rancher/scheduler:v0.8.6     "/.r/r /rancher-en..."   25 hours ago        Up About an hour                                                   r-scheduler-scheduler-1-056ab74a   2a6a718fa85d        gitlab/gitlab-ce:latest           "/assets/wrapper"        8 months ago        Restarting (127) 11 hours ago                                      gitlab


其中第一列是容器的 ID 号，它是一个重要的标识，通过 ID 号我们可以查看指定容器的日志以及启停容器等。读到这里你会发现，你已经知道了两个 ID：


一个是 IMAGE ID；
另外一个是 CONTAINER ID。


当你要删除镜像时，就需要使用到 IMAGE ID 了，也就是使用命令 docker rmi image id。那 IMAGE 和 CONTAINER 是什么关系呢？按照我的理解打个比方：


IMAGE 相当于类；
CONTAINER 相当于实例化后的对象，是在使用层面表现出来的形态。


不过你要注意的是 docker ps 只会展示运行的容器：


如果你想展示所有的容器，需要使用 docker ps -a，这个命令会展示运行的容器和已经停止的容器；
如果你机器上运行的容器很多，想看最近创建的 10 个容器，可以使用 docker ps -n 10。
如果你要停止运行某个容器，可以使用 docker stop container id 来终止，并且可以结合上文说的 docker ps -a 来看终止状态的容器；
如果要使用 docker rmi删除容器镜像，你也需要先关闭对应运行的容器才能执行删除。


值得注意的是一些初学者会误用 systemctl stop docker 这个命令，它是停止整个 Docker 服务，相当于你机器上的 Docker 全部关闭，这是初学者一定要注意到的。

作为测试或者开发，通过日志去排查问题是必不可少的，如下所示就是查看指定 Docker 容器日志的方法：

docker logs -f 3e6asb93074c7  #最后一列为容器id号


你可以将 Docker 看作是一个子系统，自然可以进入这个系统进行一定的操作。在我的使用过程中，经常会使用如下命令进入 Docker 容器找应用的 dump 信息：

docker exec -it 3e6ab93074c7 /bin/bash


以上是测试同学在使用层面最常见的命令，如果你对 Docker 还不是很了解，可以将这些作为切入点，先掌握使用，在此基础上再去了解 Docker 的架构设计以及一些进阶思想。

Dockerfile、Docker 镜像、Docker 容器的区别是什么？

上文带你熟悉了 Docker 的用法，相当于小试牛刀，可能你总听公司的人说 Dockerfile、Docker 容器、Docker 镜像，但又分不清楚，下面我就来解释下它们之间的具体区别是什么：


Dockerfile 是一个用来构建镜像的文本文件，文本内容包含了一条条构建镜像所需的指令和说明，相当于你做镜像的材料清单和执行步骤；
Docker 镜像是根据这些原材料做出来的成品；
而 Docker 容器，你可以认为是基于镜像运行的软件。


我以包饺子为例：


Dockerfile 相当于猪肉、葱姜蒜、饺子皮这些原料的描述以及包饺子的步骤；
Docker 镜像是你包完的生水饺；
而 Docker 容器则是已经煮熟可以食用的水饺了。


通过下面这个示意图可以看出从 Dockfile 到 Docker 容器的过程：



图 2：Dockfile 到 Docker 容器的过程

应用实例：如何制作基于 JMeter 的 Docker 镜像？

首先来说为什么会有这样的需求，对于用户体量比较大的公司，他们需要的系统处理能力自然也越高。在压测过程中，并不是单台压力机就可以解决问题，我们可能会在压测过程中动态调度JMeter 节点，其中一个比较方便的方式就是使用 Docker 的方式动态进行。

接下来我主要讲解如何制作基于 JMeter 的 Docker 镜像，这也是基于 Docker 扩容的关键部分。

首先我新建了一个文件夹 jmeter_docker，里面存放制作 JMeter 的 Docker 的原材料，如下所示：

[root@JD jmeter_docker]# ls

apache-jmeter-5.2.1.tgz  Dockerfile  jdk-8u101-linux-x64.tar.gz


接着我打开 Dockerfile，看看我的“原料表”里面有哪些内容，从下面的文件描述中可以看出我需要的“原料”和执行步骤：

FROM java:8

# 基础java版本

MAINTAINER cctester

# 作者

ENV http_proxy ""

ENV https_proxy ""

RUN mkdir /test && \

    chmod -R 777 /test

# 创建/test目录，用于存放jmx脚本、jtl结果文件、html测试报告文件

ENV JMETER_VERSION=5.2.1

ENV JMETER_HOME=/usr/local/apache-jmeter-${JMETER_VERSION}

ENV JMETER_PATH=${JMETER_HOME}/bin:${PATH}

ENV PATH=${JMETER_HOME}/bin:${PATH}

# 设置JMeter环境变量

ADD apache-jmeter-${JMETER_VERSION}.tgz /usr/local

# 添加JMeter

RUN ln -snf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && \

    echo "Asia/Shanghai" > /etc/timezone


在制作 JMeter 镜像时，请不要忽略后面的一个点（.），具体如下所示：

 [root@JD jmeter_docker]# docker build -t jmeter .

.....省略

  Successfully built 267c5b4303a6

# 你还可以通过docker images查看完成的镜像

[root@JD jmeter_docker]# docker images

REPOSITORY    TAG      IMAGE ID        CREATED        SIZE

jmeter      latest    267c5b4303a6   6 minutes ago   762 MB


为了方便替换压测脚本或者参数化文件，我在 jmeter_docker 文件下创建一个 test 文件夹来存放这些文件。

mkdir test

# 在当前路径创建test目录,用户存放jmeter文件

docker run -d -it --name jmeter5.2.1 -v $PWD/test:/test jmeter

31f465a1ae646c65e855084d46313754e74a2f377776d9692c0119d32949a130  //启动成功，生成运行id


然后进入容器，看下 JMeter 是否可用：

root@31f465a1ae64:/test# jmeter -v

Dec 19, 2020 6:11:34 PM java.util.prefs.FileSystemPreferences$1 run

INFO: Created user preferences directory.


到这里我们就可以运行 JMeter 进行测试了，上传一个 cctester.jmx 脚本到 test 文件夹，使用方式以及结果反馈如下所示：

root@31f465a1ae64:/test# jmeter -n -t /test/cctester.jmx 

Creating summariser <summary>

Created the tree successfully using /test/cctester.jmx

Starting standalone test @ Sat Dec 19 18:22:11 CST 2020 (1608373331470)

Waiting for possible Shutdown/StopTestNow/HeapDump/ThreadDump message on port 4445

summary +   3344 in 00:00:18 =  185.0/s Avg:    52 Min:    14 Max:  1312 Err:     0 (0.00%) Active: 10 St


到此就完成了一个基于 Docker 的 JMeter，上面演示了从制作到运行的全过程，同样对于其他Docker 的制作流程也是类似的，你可以基于一种先练习。

Docker 如何监控

通过前面章节的学习，我想对于监控你已经并不陌生，并且可以提炼出一套搭建监控体系的方法，对于 Docker 监控本质上也是换汤不换药，我主要进行思路上的一些讲解。

Docker 本身也是可以通过命令行来监控的，看下 docker stats 的输出，如下所示：

CONTAINER           CPU %               MEM USAGE / LIMIT  MEM %               NET I/O             BLOCK I/O     PIDS

b667f6b988b4        0.07%               381.3 MiB / 7.64 GiB   4.87%               119 MB / 105 MB     275 MB / 0 B        61

f650d561b729        0.04%               233.1 MiB / 7.64 GiB   2.98%               94.9 MB / 118 MB    139 MB / 397 MB     49

c7575bf9a7d7        0.00%               4.711 MiB / 7.64 GiB   0.06%               0 B / 0 B           954 kB / 0 B        6

2a72f849baaa        0.10%               4.008 MiB / 7.64 GiB   0.05%               18.8 MB / 14.5 MB   68.5 MB / 3.04 MB   6

760e653d4324        0.00%               4.887 MiB / 7.64 GiB   0.06%               0 B / 0 B           92.5 MB / 4.1 kB    27


你可以看到不同的实例都有对应包括 CPU、内存、磁盘、网络的监控，这样的数据比较详细直观。所以这一讲我给你留一个作业，自行搭建 Docker 的可视化监控，可以结合之前讲过的 Grafana、Promethues 等，欢迎在评论区留下你搭建过程中的心得体会以及问题。

总结

本讲作为第三模块的收尾，带你学习了 Docker 的基础知识，包括镜像制作、运行，以及监控的常见方式。通过对第三模块的系统学习，你也应该掌握常见的监控方法以及监控部署开展的思路。

                        
                        
                            