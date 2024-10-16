
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            22  多阶级构建：Docker 下如何实现镜像多阶级构建？
                            通过前面课程的学习，我们知道 Docker 镜像是分层的，并且每一层镜像都会额外占用存储空间，一个 Docker 镜像层数越多，这个镜像占用的存储空间则会越多。镜像构建最重要的一个原则就是要保持镜像体积尽可能小，要实现这个目标通常可以从两个方面入手：


基础镜像体积应该尽量小；
尽量减少 Dockerfile 的行数，因为 Dockerfile 的每一条指令都会生成一个镜像层。


在 Docker 的早期版本中，对于编译型语言（例如 C、Java、Go）的镜像构建，我们只能将应用的编译和运行环境的准备，全部都放到一个 Dockerfile 中，这就导致我们构建出来的镜像体积很大，从而增加了镜像的存储和分发成本，这显然与我们的镜像构建原则不符。

为了减小镜像体积，我们需要借助一个额外的脚本，将镜像的编译过程和运行过程分开。


编译阶段：负责将我们的代码编译成可执行对象。
运行时构建阶段：准备应用程序运行的依赖环境，然后将编译后的可执行对象拷贝到镜像中。


我以 Go 语言开发的一个 HTTP 服务为例，代码如下：

package main

import (

   "fmt"

   "net/http"

)

func hello(w http.ResponseWriter, req *http.Request) {

   fmt.Fprintf(w, "hello world!\n")

}

func main() {

   http.HandleFunc("/", hello)

   http.ListenAndServe(":8080", nil)

}


我将这个 Go 服务构建成镜像分为两个阶段：代码的编译阶段和镜像构建阶段。

我们构建镜像时，镜像中需要包含 Go 语言编译环境，应用的编译阶段我们可以使用 Dockerfile.build 文件来构建镜像。Dockerfile.build 的内容如下：

FROM golang:1.13

WORKDIR /go/src/github.com/wilhelmguo/multi-stage-demo/

COPY main.go .

RUN CGO_ENABLED=0 GOOS=linux go build -o http-server .


Dockerfile.build 可以帮助我们把代码编译成可以执行的二进制文件，我们使用以下 Dockerfile 构建一个运行环境：

FROM alpine:latest  

WORKDIR /root/

COPY http-server .

CMD ["./http-server"] 


然后，我们将应用的编译和运行环境的准备步骤，都放到一个 build.sh 脚本文件中，内容如下：

#!/bin/sh

echo Building http-server:build

docker build -t http-server:build . -f Dockerfile.build

docker create --name builder http-server:build  

docker cp builder:/go/src/github.com/wilhelmguo/multi-stage-demo/http-server ./http-server  

docker rm -f builder

echo Building http-server:latest

docker build -t http-server:latest .

rm ./http-server


下面，我带你来逐步分析下这个脚本。

第一步，声明 shell 文件，然后输出开始构建信息。

#!/bin/sh

echo Building http-server:build


第二步，使用 Dockerfile.build 文件来构建一个临时镜像 http-server:build。

docker build -t http-server:build . -f Dockerfile.build


第三步，使用 http-server:build 镜像创建一个名称为 builder 的容器，该容器包含编译后的 http-server 二进制文件。

docker create --name builder http-server:build  


第四步，使用docker cp命令从 builder 容器中拷贝 http-server 文件到当前构建目录下，并且删除名称为 builder 的临时容器。

docker cp builder:/go/src/github.com/wilhelmguo/multi-stage-demo/http-server ./http-server  

docker rm -f builder


第五步，输出开始构建镜像信息。

echo Building http-server:latest


第六步，构建运行时镜像，然后删除临时文件 http-server。

docker build -t http-server:latest .

rm ./http-server


我这里总结一下，我们是使用 Dockerfile.build 文件来编译应用程序，使用 Dockerfile 文件来构建应用的运行环境。然后我们通过创建一个临时容器，把编译后的 http-server 文件拷贝到当前构建目录中，然后再把这个文件拷贝到运行环境的镜像中，最后指定容器的启动命令为 http-server。

使用这种方式虽然可以实现分离镜像的编译和运行环境，但是我们需要额外引入一个 build.sh 脚本文件，而且构建过程中，还需要创建临时容器 builder 拷贝编译后的 http-server 文件，这使得整个构建过程比较复杂，并且整个构建过程也不够透明。

为了解决这种问题， Docker 在 17.05 推出了多阶段构建（multistage-build）的解决方案。

使用多阶段构建

Docker 允许我们在 Dockerfile 中使用多个 FROM 语句，而每个 FROM 语句都可以使用不同基础镜像。最终生成的镜像，是以最后一条 FROM 为准，所以我们可以在一个 Dockerfile 中声明多个 FROM，然后选择性地将一个阶段生成的文件拷贝到另外一个阶段中，从而实现最终的镜像只保留我们需要的环境和文件。多阶段构建的主要使用场景是分离编译环境和运行环境。

接下来，我们使用多阶段构建的特性，将上述未使用多阶段构建的过程精简成如下 Dockerfile：

FROM golang:1.13

WORKDIR /go/src/github.com/wilhelmguo/multi-stage-demo/

COPY main.go .

RUN CGO_ENABLED=0 GOOS=linux go build -o http-server .

FROM alpine:latest  

WORKDIR /root/

COPY --from=0 /go/src/github.com/wilhelmguo/multi-stage-demo/http-server .

CMD ["./http-server"] 


然后，我们将这个 Dockerfile 拆解成两步进行分析。

第一步，编译代码。

FROM golang:1.13

WORKDIR /go/src/github.com/wilhelmguo/multi-stage-demo/

COPY main.go .

RUN CGO_ENABLED=0 GOOS=linux go build -o http-server .


将代码拷贝到 golang:1.13 镜像（已经安装好了 go）中，并且使用go build命令编译代码生成 http-server 文件。

第二步，构建运行时镜像。

FROM alpine:latest  

WORKDIR /root/

COPY --from=0 /go/src/github.com/wilhelmguo/multi-stage-demo/http-server .

CMD ["./http-server"]


使用第二个 FROM 命令表示镜像构建的第二阶段，使用 COPY 指令拷贝编译后的文件到 alpine 镜像中，–from=0 表示从第一阶段构建结果中拷贝文件到当前构建阶段。

最后，我们只需要使用以下命令，即可实现整个镜像的构建：

docker build -t http-server:latest .


构建出来的镜像与未使用多阶段构建之前构建的镜像大小一致，为了验证这一结论，我们分别使用这两种方式来构建镜像，最后对比一下镜像构建的结果。

镜像构建对比

使用多阶段构建前后的代码我都已经放在了Github，你只需要克隆代码到本地即可。

$ mkdir /go/src/github.com/wilhelmguo

$ cd /go/src/github.com/wilhelmguo

$ git clone https://github.com/wilhelmguo/multi-stage-demo.git


代码克隆完成后，我们首先切换到without-multi-stage分支：

$ cd without-multi-stage

$ git checkout without-multi-stage


这个分支是未使用多阶段构建技术构建镜像的代码，我们可以通过执行 build.sh 文件构建镜像：

$  chmod +x build.sh && ./build.sh

Building http-server:build

Sending build context to Docker daemon  96.26kB

Step 1/4 : FROM golang:1.13

1.13: Pulling from library/golang

d6ff36c9ec48: Pull complete 

c958d65b3090: Pull complete 

edaf0a6b092f: Pull complete 

80931cf68816: Pull complete 

813643441356: Pull complete 

799f41bb59c9: Pull complete 

16b5038bccc8: Pull complete 

Digest: sha256:8ebb6d5a48deef738381b56b1d4cd33d99a5d608e0d03c5fe8dfa3f68d41a1f8

Status: Downloaded newer image for golang:1.13

 ---> d6f3656320fe

Step 2/4 : WORKDIR /go/src/github.com/wilhelmguo/multi-stage-demo/

 ---> Running in fa3da5ffb0c0

Removing intermediate container fa3da5ffb0c0

 ---> 97245cbb773f

Step 3/4 : COPY main.go .

 ---> a021d2f2a5bb

Step 4/4 : RUN CGO_ENABLED=0 GOOS=linux go build -o http-server .

 ---> Running in b5c36bb67b9c

Removing intermediate container b5c36bb67b9c

 ---> 76c0c88a5cf7

Successfully built 76c0c88a5cf7

Successfully tagged http-server:build

4b0387b270bc4a4da570e1667fe6f9baac765f6b80c68f32007494c6255d9e5b

builder

Building http-server:latest

Sending build context to Docker daemon  7.496MB

Step 1/4 : FROM alpine:latest

latest: Pulling from library/alpine

df20fa9351a1: Already exists 

Digest: sha256:185518070891758909c9f839cf4ca393ee977ac378609f700f60a771a2dfe321

Status: Downloaded newer image for alpine:latest

 ---> a24bb4013296

Step 2/4 : WORKDIR /root/

 ---> Running in 0b25ffe603b8

Removing intermediate container 0b25ffe603b8

 ---> 80da40d3a0b4

Step 3/4 : COPY http-server .

 ---> 3f2300210b7b

Step 4/4 : CMD ["./http-server"]

 ---> Running in 045cea651dde

Removing intermediate container 045cea651dde

 ---> 5c73883177e7

Successfully built 5c73883177e7

Successfully tagged http-server:latest


经过一段时间的等待，我们的镜像就构建完成了。
镜像构建完成后，我们使用docker image ls命令查看一下刚才构建的镜像大小：

$ docker image ls http-server

REPOSITORY          TAG                 IMAGE ID            CREATED             SIZE

http-server         latest              5c73883177e7        3 minutes ago       13MB

http-server         build               76c0c88a5cf7        3 minutes ago       819MB


可以看到，http-server:latest 镜像只有 13M，而我们的编译镜像 http-server:build 则为 819M，虽然我们编写了很复杂的脚本 build.sh，但是这个脚本确实帮助我们将镜像体积减小了很多。

下面，我们将代码切换到多阶段构建分支：

$ git checkout with-multi-stage

Switched to branch 'with-multi-stage'


为了避免镜像名称重复，我们将多阶段构建的镜像命名为 http-server-with-multi-stage:latest ，并且禁用缓存，避免缓存干扰构建结果，构建命令如下：

$ docker build --no-cache -t http-server-with-multi-stage:latest .

Sending build context to Docker daemon  96.77kB

Step 1/8 : FROM golang:1.13

 ---> d6f3656320fe

Step 2/8 : WORKDIR /go/src/github.com/wilhelmguo/multi-stage-demo/

 ---> Running in 640da7a92a62

Removing intermediate container 640da7a92a62

 ---> 9c27b4606da0

Step 3/8 : COPY main.go .

 ---> bd9ce4af24cb

Step 4/8 : RUN CGO_ENABLED=0 GOOS=linux go build -o http-server .

 ---> Running in 6b441b4cc6b7

Removing intermediate container 6b441b4cc6b7

 ---> 759acbf6c9a6

Step 5/8 : FROM alpine:latest

 ---> a24bb4013296

Step 6/8 : WORKDIR /root/

 ---> Running in c2aa2168acd8

Removing intermediate container c2aa2168acd8

 ---> f026884acda6

Step 7/8 : COPY --from=0 /go/src/github.com/wilhelmguo/multi-stage-demo/http-server .

 ---> 667503e6bc14

Step 8/8 : CMD ["./http-server"]

 ---> Running in 15c4cc359144

Removing intermediate container 15c4cc359144

 ---> b73cc4d99088

Successfully built b73cc4d99088

Successfully tagged http-server-with-multi-stage:latest


镜像构建完成后，我们同样使用docker image ls命令查看一下镜像构建结果：

$ docker image ls http-server-with-multi-stage:latest

REPOSITORY                     TAG                 IMAGE ID            CREATED             SIZE

http-server-with-multi-stage   latest              b73cc4d99088        2 minutes ago       13MB


可以看到，使用多阶段构建的镜像大小与上一步构建的镜像大小一致，都为 13M。但是使用多阶段构建后，却大大减少了我们的构建步骤，使得构建过程更加清晰可读。

多阶段构建的其他使用方式

多阶段构建除了我们上面讲解的使用方式，还有更多其他的使用方式，这些使用方式，可以使得多阶段构建实现更多的功能。

为构建阶段命名

默认情况下，每一个构建阶段都没有被命名，你可以通过 FROM 指令出现的顺序来引用这些构建阶段，构建阶段的序号是从 0 开始的。然而，为了提高 Dockerfile 的可读性，我们需要为某些构建阶段起一个名称，这样即便后面我们对 Dockerfile 中的内容进程重新排序或者添加了新的构建阶段，其他构建过程中的 COPY 指令也不需要修改。

上面的 Dockerfile 我们可以优化成如下内容：

FROM golang:1.13 AS builder

WORKDIR /go/src/github.com/wilhelmguo/multi-stage-demo/

COPY main.go .

RUN CGO_ENABLED=0 GOOS=linux go build -o http-server .

FROM alpine:latest  

WORKDIR /root/

COPY --from=builder /go/src/github.com/wilhelmguo/multi-stage-demo/http-server .

CMD ["./http-server"]


我们在第一个构建阶段，使用 AS 指令将这个阶段命名为 builder。然后在第二个构建阶段使用 –from=builder 指令，即可从第一个构建阶段中拷贝文件，使得 Dockerfile 更加清晰可读。

停止在特定的构建阶段

有时候，我们的构建阶段非常复杂，我们想在代码编译阶段进行调试，但是多阶段构建默认构建 Dockerfile 的所有阶段，为了减少每次调试的构建时间，我们可以使用 target 参数来指定构建停止的阶段。

例如，我只想在编译阶段调试 Dockerfile 文件，可以使用如下命令：

$ docker build --target builder -t http-server:latest .


在执行docker build命令时添加 target 参数，可以将构建阶段停止在指定阶段，从而方便我们调试代码编译过程。

使用现有镜像作为构建阶段

使用多阶段构建时，不仅可以从 Dockerfile 中已经定义的阶段中拷贝文件，还可以使用COPY --from指令从一个指定的镜像中拷贝文件，指定的镜像可以是本地已经存在的镜像，也可以是远程镜像仓库上的镜像。

例如，当我们想要拷贝 nginx 官方镜像的配置文件到我们自己的镜像中时，可以在 Dockerfile 中使用以下指令：

COPY --from=nginx:latest /etc/nginx/nginx.conf /etc/local/nginx.conf


从现有镜像中拷贝文件还有一些其他的使用场景。例如，有些工具没有我们使用的操作系统的安装源，或者安装源太老，需要我们自己下载源码并编译这些工具，但是这些工具可能依赖的编译环境非常复杂，而网上又有别人已经编译好的镜像。这时我们就可以使用COPY --from指令从编译好的镜像中将工具拷贝到我们自己的镜像中，很方便地使用这些工具了。

结语

多阶段构建可以让我们通过一个 Dockerfile 很方便地构建出体积更小的镜像，并且我们只需要编写 Dockerfile 文件即可，无须借助外部脚本文件。这使得镜像构建过程更加简单透明，但要提醒一点：使用多阶段构建的唯一限制条件是我们使用的 Docker 版本必须高于 17.05 。

                        
                        
                            