
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            17  原理实践：自己动手使用 Golang 开发 Docker（上）
                            第一模块，我们从 Docker 基础概念讲到 Docker 的基本操作。第二模块，我们详细剖析了 Docker 的三大关键技术（ Namespace、cgroups 和联合文件系统）的实现原理，并且讲解了 Docker 的网络模型等关键性技术。相信此时的你已经对 Docker 有了一个新的认识。

接下来的两课时，我就趁热打铁，带你动手使用 Golang 编写一个 Docker。学习这两节的内容需要你能够熟练使用 Golang 语言，如果你没有 Golang 编程基础，建议先学习一下 Golang 的基本语法。那么 Golang 究竟是什么呢? Golang 应该如何安装使用？下面我带你一一学习。

Golang 是什么?

Golang 又称为 Go，是 Google 开源的一种静态编译型语言，Golang 自带内存管理机制，相比于 C 和 C++ 语言，我们不需要关心内存的分配和回收。

Golang 是新一代的互联网编程语言，在 Golang 诞生前，C 或 C++ 作为服务端高性能编程语言，使用 C 或 C++ 开发的业务具有非常高的执行效率，但是编译和开发效率却不尽人意，Java、.NET 等语言的诞生大大提高了软件开发速度，但是运行效率和资源占用却不如 C 和 C++。

这时 Golang 横空出世，由于 Golang 较高的开发效率和执行效率，很快便从众多编程语言中脱颖而出，成为众多互联网公司的新宠儿。滴滴、知乎、阿里等众多大型互联网公司都在大量使用 Golang。 同时，Docker 和 Kubernetes 等众多明星项目也都是使用 Golang 开发的。因此，熟练掌握 Golang 将会为你加分很多。

这么好的编程语言，你是不是已经迫不及待地想要安装体验一下了？别着急，下面我带你来安装一个 Golang 环境。

Golang 安装

安装信息如下：


CentOS 7系统
Golang 版本 1.15.2


首先我们到Golang 官网（由于国内无法访问 Golang 官网，推荐到Golang 中文网下载安装包）下载一个对应操作系统的安装包。

$ cd /tmp && wget https://studygolang.com/dl/golang/go1.15.2.linux-amd64.tar.gz


解压缩安装包：

$ sudo tar -C /usr/local -xzf go1.15.2.linux-amd64.tar.gz


在 $HOME/.bashrc 文件末尾添加以下内容，将 Golang 可执行文件目录添加到系统 PATH 中：

export PATH=$PATH:/usr/local/go/bin


将 go 的安装路径添加到系统 PATH 中后，就可以在命令行直接使用 go 命令了。配置好 go 命令后，我们还需要配置 GOPATH 才能正确存放和编译我们的 go 代码。

配置 GOPATH

GOPATH 是 Golang 的源码和相关编译文件的存放路径，GOPATH 路径下有三个文件夹 src、pkg 和 bin，它们的用途分别是：




目录
用途





src
源代码存放路径或者引用的外部库



pkg
编译时生成的对象文件



bin
编译后的可执行二进制



这里我们开始配置 GOPATH 路径为 /go。首先准备相关的目录：

$ sudo mkdir /go

$ sudo mkdir /go/src

$ sudo mkdir /go/pkg

$ sudo mkdir /go/bin


然后将 GOPATH 添加到 $HOME/.bashrc 文件末尾，并且把 GOPATH 下的 bin 目录也添加到系统的 PATH 中，这样方便程序编译后直接使用。添加的内容如下：

export GOPATH=/go

export PATH=$PATH:$GOPATH/bin

# 设置 Golang 的代理，方便我们顺利下载依赖包

export GOPROXY="https://goproxy.io,direct"


接下来，使用 source $HOME/.bashrc 命令生效一下我们的配置，然后我们再使用 go env 命令查看一下我们的配置结果：

$ go env

GO111MODULE=""

GOARCH="amd64"

GOBIN=""

GOCACHE="/root/.cache/go-build"

GOENV="/root/.config/go/env"

GOEXE=""

GOFLAGS=""

GOHOSTARCH="amd64"

GOHOSTOS="linux"

GOINSECURE=""

GOMODCACHE="/go/pkg/mod"

GONOPROXY=""

GONOSUMDB=""

GOOS="linux"

GOPATH="/go"

GOPRIVATE=""

GOPROXY="https://goproxy.io,direct"

GOROOT="/usr/local/go"

GOSUMDB="sum.golang.org"

GOTMPDIR=""

GOTOOLDIR="/usr/local/go/pkg/tool/linux_amd64"

GCCGO="gccgo"

AR="ar"

CC="gcc"

CXX="g++"

CGO_ENABLED="1"

GOMOD=""

CGO_CFLAGS="-g -O2"

CGO_CPPFLAGS=""

CGO_CXXFLAGS="-g -O2"

CGO_FFLAGS="-g -O2"

CGO_LDFLAGS="-g -O2"

PKG_CONFIG="pkg-config"

GOGCCFLAGS="-fPIC -m64 -pthread -fmessage-length=0 -fdebug-prefix-map=/tmp/go-build352828668=/tmp/go-build -gno-record-gcc-switches"


从 GOPATH 和 GOPROXY 两个变量的结果，可以看到 GOPATH 和 GOPROXY 均已经生效。到此，我们的 Golang 已经安装完毕。下面，我们就开始真正的 Docker 编写之旅吧。

编写 Docker

在开始编写 Docker 之前，我先介绍几个基础知识，如果你对这些基础知识已经很熟悉了，可以直接跳过这块的基础知识。

Linux Proc 文件系统

Linux 系统中，/proc 目录是一种“文件系统”，这里我用了引号，其实 /proc 目录并不是一个真正的文件系统。/proc 目录存放于内存中，是一个虚拟的文件系统，该目录存放了当前内核运行状态的一系列特殊的文件，你可以通过这些文件查看当前的进程信息。

下面，我们通过 ls 命令查看一下 /proc 目录下的内容：

$ sudo ls -l /proc

total 0

dr-xr-xr-x  9 root    root                  0 Sep 19 21:34 1

dr-xr-xr-x  9 root    root                  0 Sep 19 21:34 30097

...省略部分输出

dr-xr-xr-x  9 root    root                  0 Sep 19 21:34 8

dr-xr-xr-x  9 root    root                  0 Sep 19 21:34 9

dr-xr-xr-x  9 root    root                  0 Sep 19 21:34 97

dr-xr-xr-x  2 root    root                  0 Sep 19 22:27 acpi

-r--r--r--  1 root    root                  0 Sep 19 22:27 buddyinfo

dr-xr-xr-x  4 root    root                  0 Sep 19 22:27 bus

-r--r--r--  1 root    root                  0 Sep 19 22:27 cgroups

-r--r--r--  1 root    root                  0 Sep 19 22:27 cmdline

-r--r--r--  1 root    root                  0 Sep 19 22:27 consoles

-r--r--r--  1 root    root                  0 Sep 19 22:27 cpuinfo

-r--r--r--  1 root    root                  0 Sep 19 22:27 crypto

-r--r--r--  1 root    root                  0 Sep 19 22:27 devices

-r--r--r--  1 root    root                  0 Sep 19 21:34 diskstats

-r--r--r--  1 root    root                  0 Sep 19 22:27 dma

dr-xr-xr-x  2 root    root                  0 Sep 19 22:27 driver

-r--r--r--  1 root    root                  0 Sep 19 22:27 execdomains

-r--r--r--  1 root    root                  0 Sep 19 22:27 fb

-r--r--r--  1 root    root                  0 Sep 19 22:27 filesystems

dr-xr-xr-x  5 root    root                  0 Sep 19 22:27 fs

-r--r--r--  1 root    root                  0 Sep 19 22:27 interrupts

-r--r--r--  1 root    root                  0 Sep 19 22:27 iomem

-r--r--r--  1 root    root                  0 Sep 19 22:27 ioports

dr-xr-xr-x 27 root    root                  0 Sep 19 22:27 irq

-r--r--r--  1 root    root                  0 Sep 19 22:27 kallsyms

-r--------  1 root    root    140737486266368 Sep 19 22:27 kcore

-r--r--r--  1 root    root                  0 Sep 19 22:27 key-users

-r--r--r--  1 root    root                  0 Sep 19 22:27 keys

-r--------  1 root    root                  0 Sep 19 22:27 kmsg

-r--------  1 root    root                  0 Sep 19 22:27 kpagecount

-r--------  1 root    root                  0 Sep 19 22:27 kpageflags

-r--r--r--  1 root    root                  0 Sep 19 22:27 loadavg

-r--r--r--  1 root    root                  0 Sep 19 22:27 locks

-r--r--r--  1 root    root                  0 Sep 19 22:27 mdstat

-r--r--r--  1 root    root                  0 Sep 19 22:27 meminfo

-r--r--r--  1 root    root                  0 Sep 19 22:27 misc

-r--r--r--  1 root    root                  0 Sep 19 22:27 modules

lrwxrwxrwx  1 root    root                 11 Sep 19 22:27 mounts -> self/mounts

-rw-r--r--  1 root    root                  0 Sep 19 22:27 mtrr

lrwxrwxrwx  1 root    root                  8 Sep 19 22:27 net -> self/net

-r--r--r--  1 root    root                  0 Sep 19 22:27 pagetypeinfo

-r--r--r--  1 root    root                  0 Sep 19 22:27 partitions

-r--r--r--  1 root    root                  0 Sep 19 22:27 sched_debug

-r--r--r--  1 root    root                  0 Sep 19 22:27 schedstat

dr-xr-xr-x  2 root    root                  0 Sep 19 22:27 scsi

lrwxrwxrwx  1 root    root                  0 Sep 19 21:34 self -> 30097

-r--------  1 root    root                  0 Sep 19 22:27 slabinfo

-r--r--r--  1 root    root                  0 Sep 19 22:27 softirqs

-r--r--r--  1 root    root                  0 Sep 19 21:34 stat

-r--r--r--  1 root    root                  0 Sep 19 21:34 swaps

dr-xr-xr-x  1 root    root                  0 Sep 19 21:34 sys

--w-------  1 root    root                  0 Sep 19 22:27 sysrq-trigger

dr-xr-xr-x  2 root    root                  0 Sep 19 22:27 sysvipc

-r--r--r--  1 root    root                  0 Sep 19 22:27 timer_list

-rw-r--r--  1 root    root                  0 Sep 19 22:27 timer_stats

dr-xr-xr-x  4 root    root                  0 Sep 19 22:27 tty

-r--r--r--  1 root    root                  0 Sep 19 22:27 uptime

-r--r--r--  1 root    root                  0 Sep 19 22:27 version

-r--------  1 root    root                  0 Sep 19 22:27 vmallocinfo

-r--r--r--  1 root    root                  0 Sep 19 22:27 vmstat

-r--r--r--  1 root    root                  0 Sep 19 22:27 zoneinfo


可以看到，这个目录下有很多数字，这些数字目录实际上是以进程 ID 命名的。除了这些以进程 ID 命名的目录，还有一些特殊的目录，这里我讲解一下与我们编写 Docker 有关的文件和目录。


self 目录：它是连接到当前正在运行的进程目录，比如我当前的进程 ID 为 30097，则 self 目录实际连接到 /proc/30097 这个目录。
/proc/{PID}/exe 文件：exe 连接到进程执行的命令文件，例如 30097 这个进程的运行命令为 docker，则执行 /proc/30097/exe ps 等同于执行 docker ps。


好了，了解完这些基础知识后，我们就开始行动吧！因为我们的精简版 Docker 是使用 Golang 编写，这里就给我们编写的 Docker 命名为 gocker 吧。

实现 gocker 的 run 命令

通过前面的章节，我们学习了要运行一个容器，必须先有镜像。这里我们首先准备一个 busybox 镜像，以便我们运行 gocker 容器。

$ mkdir /tmp/busybox && cd /tmp/busybox

$ docker export $(docker create busybox) -o busybox.tar

$ tar -xf busybox.tar


以上是我们在 /tmp/busybox 目录，使用 docker export 命令导出的一个 busybox 镜像文件，然后对镜像文件包进行解压，解压后 /tmp/busybox 目录内容如下：

$ ls -l /tmp/busybox/

total 1472

drwxr-xr-x 2 root      root        12288 Sep  9 02:09 bin

-rw------- 1 root      root      1455104 Sep 19 22:47 busybox.tar

drwxr-xr-x 4 root      root         4096 Sep 19 16:41 dev

drwxr-xr-x 3 root      root         4096 Sep 19 16:41 etc

drwxr-xr-x 2 nfsnobody nfsnobody    4096 Sep  9 02:09 home

drwxr-xr-x 2 root      root         4096 Sep 19 16:41 proc

drwx------ 2 root      root         4096 Sep 19 21:07 root

drwxr-xr-x 2 root      root         4096 Sep 19 16:41 sys

drwxrwxrwt 2 root      root         4096 Sep  9 02:09 tmp

drwxr-xr-x 3 root      root         4096 Sep  9 02:09 usr

drwxr-xr-x 4 root      root         4096 Sep  9 02:09 var


准备好镜像文件后，把我为你准备好的 gocker 代码下载下来吧，这里我使用手动下载源码的方式克隆代码：

$ mkdir -p /go/src/github.com/wilhelmguo

$ cd /go/src/github.com/wilhelmguo && git clone https://github.com/wilhelmguo/gocker.git

$ cd gocker

$ git checkout lesson-17



我的 GOPATH 在 /go 目录下，如果你的 GOPATH 跟我不一致，请根据 GOPATH 存放和编译源码。本课时的源码存放在这里，你也可以在线阅读。


代码下载完后，我们进入 gocker 的目录，查看下源码文件：

$ tree .

.

|-- go.mod

|-- go.sum

|-- main.go

|-- README.md

|-- runc

|   `-- run.go

`-- vendor

... 省略 vendor 目录结构

15 directories, 59 files



本项目使用 go mod 管理包依赖，go mod 是在 golang 1.11 版本加入的新的特性，是用来管理包的依赖的，也是目前官方的包依赖管理工具。如果你想学习更多个 go mod 使用方法，可以参考官网。


可以看到该源码下有两个主要文件：一个是 main.go 文件，这是 gocker 的主入口函数；另外一个是 run.go ，这个文件是 gocker run 命令的具体实现。

下面我们使用 go install 命令来编译一下我们的 gocker 项目：

$ go install


执行完 go install 后， Golang 会自动帮助我们编译当前项目下的代码，编译后的二进制文件存放在 \(GOPATH/bin 目录下。由于我们之前在 \)HOME/.bashrc 文件下把 $GOPATH/bin 放入了系统 PATH 中，所以此时你可以直接使用 gocker 命令了。
接下来我们使用 gocker 来启动一个容器：

# gocker run -it -rootfs=/tmp/busybox /bin/sh

2020/09/19 23:46:27 Current path is  /tmp/busybox

2020/09/19 23:46:27 CmdArray is  [/bin/sh]

/ #



如果出现 pivotRoot error pivot_root invalid argument 的报错，可以先执行 unshare -m 命令，然后使用 rm -rf /tmp/busybox/.pivot_root 命令删除临时文件，再次重试即可。


这里我们使用 it 参数指定以命令行交互的模式启动容器，rootfs 指定准备好的镜像目录。执行完上面的命令后 busybox 容器就成功启动了。
这时候，我们使用 ps 命令查看一下当前进程信息：

/ # /bin/ps -ef

PID   USER     TIME  COMMAND

    1 root      0:00 /bin/sh

    5 root      0:00 /bin/ps -ef


此时，容器内的进程已经与主机完全隔离。
我们再查看一下当前目录下的内容：

/ # pwd

/ # /bin/ls -l

total 1468

drwxr-xr-x    2 root     root         12288 Sep  8 18:09 bin

-rw-------    1 root     root       1455104 Sep 19 14:47 busybox.tar

drwxr-xr-x    4 root     root          4096 Sep 19 08:41 dev

drwxr-xr-x    3 root     root          4096 Sep 19 08:41 etc

drwxr-xr-x    2 nobody   nobody        4096 Sep  8 18:09 home

dr-xr-xr-x  122 root     root             0 Sep 19 15:46 proc

drwx------    2 root     root          4096 Sep 19 13:07 root

drwxr-xr-x    2 root     root          4096 Sep 19 08:41 sys

drwxrwxrwt    2 root     root          4096 Sep  8 18:09 tmp

drwxr-xr-x    3 root     root          4096 Sep  8 18:09 usr

drwxr-xr-x    4 root     root          4096 Sep  8 18:09 var


可以看到当前目录已经为根目录，并且根目录下的文件就是我们上面准备的 busybox 镜像文件。
到此，一个完全由我们自己编写的 gocker 已经可以启动容器了。

结语

本课时我们讲解了 Golang 是什么, 并且配置好了 Golang 环境，编译了 gocker，也了解了 Linux /proc 文件系统的一些重要功能，最后使用 gocker 成功启动了一个 busybox 容器。

那么你知道，为什么 Docker 会选择使用 Golang 来开发吗？思考后，把你的想法写在留言区。

下一课时我将为你全面剖析 gocker 的源码以及它的实现原理，让你能够自己动手把它写出来，到时见。

                        
                        
                            