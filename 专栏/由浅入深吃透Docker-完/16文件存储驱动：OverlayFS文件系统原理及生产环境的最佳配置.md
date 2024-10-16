
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            16  文件存储驱动：OverlayFS 文件系统原理及生产环境的最佳配置
                            前面课时我分别介绍了 Docker 常见的联合文件系统解决方案： AUFS 和 Devicemapper。今天我给你介绍一个性能更好的联合文件系统解决方案—— OverlayFS。

OverlayFS 的发展分为两个阶段。2014 年，OverlayFS 第一个版本被合并到 Linux 内核 3.18 版本中，此时的 OverlayFS 在 Docker 中被称为overlay文件驱动。由于第一版的overlay文件系统存在很多弊端（例如运行一段时间后Docker 会报 “too many links problem” 的错误）， Linux 内核在 4.0 版本对overlay做了很多必要的改进，此时的 OverlayFS 被称之为overlay2。

因此，在 Docker 中 OverlayFS 文件驱动被分为了两种，一种是早期的overlay，不推荐在生产环境中使用，另一种是更新和更稳定的overlay2，推荐在生产环境中使用。下面的内容我们主要围绕overlay2展开。

使用 overlay2 的先决条件

overlay2虽然很好，但是它的使用是有一定条件限制的。


要想使用overlay2，Docker 版本必须高于 17.06.02。
如果你的操作系统是 RHEL 或 CentOS，Linux 内核版本必须使用 3.10.0-514 或者更高版本，其他 Linux 发行版的内核版本必须高于 4.0（例如 Ubuntu 或 Debian），你可以使用uname -a查看当前系统的内核版本。
overlay2最好搭配 xfs 文件系统使用，并且使用 xfs 作为底层文件系统时，d_type必须开启，可以使用以下命令验证 d_type 是否开启：


$ xfs_info /var/lib/docker | grep ftype

naming   =version 2              bsize=4096   ascii-ci=0 ftype=1


当输出结果中有 ftype=1 时，表示 d_type 已经开启。如果你的输出结果为 ftype=0，则需要重新格式化磁盘目录，命令如下：

$ sudo mkfs.xfs -f -n ftype=1 /path/to/disk


另外，在生产环境中，推荐挂载 /var/lib/docker 目录到单独的磁盘或者磁盘分区，这样可以避免该目录写满影响主机的文件写入，并且把挂载信息写入到 /etc/fstab，防止机器重启后挂载信息丢失。

挂载配置中推荐开启 pquota，这样可以防止某个容器写文件溢出导致整个容器目录空间被占满。写入到 /etc/fstab 中的内容如下：

$UUID /var/lib/docker xfs defaults,pquota 0 0


其中 UUID 为 /var/lib/docker 所在磁盘或者分区的 UUID 或者磁盘路径。
如果你的操作系统无法满足上面的任何一个条件，那我推荐你使用 AUFS 或者 Devicemapper 作为你的 Docker 文件系统驱动。


通常情况下， overlay2 会比 AUFS 和 Devicemapper 性能更好，而且更加稳定，因为 overlay2 在 inode 优化上更加高效。因此在生产环境中推荐使用 overlay2 作为 Docker 的文件驱动。


下面我通过实例来教你如何初始化 /var/lib/docker 目录，为后面配置 Docker 的overlay2文件驱动做准备。

准备 /var/lib/docker 目录

1.使用 lsblk（Linux 查看磁盘和块设备信息命令）命令查看本机磁盘信息：

$ lsblk

NAME   MAJ:MIN RM  SIZE RO TYPE MOUNTPOINT

vda    253:0    0  500G  0 disk

`-vda1 253:1    0  500G  0 part /

vdb    253:16   0  500G  0 disk

`-vdb1 253:17   0    8G  0 part


可以看到，我的机器有两块磁盘，一块是 vda，一块是 vdb。其中 vda 已经被用来挂载系统根目录，这里我想把 /var/lib/docker 挂载到 vdb1 分区上。

2.使用 mkfs 命令格式化磁盘 vdb1：

$ sudo mkfs.xfs -f -n ftype=1 /dev/vdb1


3.将挂载信息写入到 /etc/fstab，保证机器重启挂载目录不丢失：

$ sudo echo "/dev/vdb1 /var/lib/docker xfs defaults,pquota 0 0" >> /etc/fstab


4.使用 mount 命令使得挂载目录生效：

$ sudo mount -a


5.查看挂载信息：

$ lsblk

NAME   MAJ:MIN RM  SIZE RO TYPE MOUNTPOINT

vda    253:0    0  500G  0 disk

`-vda1 253:1    0  500G  0 part /

vdb    253:16   0  500G  0 disk

`-vdb1 253:17   0    8G  0 part /var/lib/docker


可以看到此时 /var/lib/docker 目录已经被挂载到了 vdb1 这个磁盘分区上。我们使用 xfs_info 命令验证下 d_type 是否已经成功开启：

$ xfs_info /var/lib/docker | grep ftype

naming   =version 2              bsize=4096   ascii-ci=0 ftype=1


可以看到输出结果为 ftype=1，证明 d_type 已经被成功开启。

准备好 /var/lib/docker 目录后，我们就可以配置 Docker 的文件驱动为 overlay2，并且启动 Docker 了。

如何在 Docker 中配置 overlay2？

当你的系统满足上面的条件后，就可以配置你的 Docker 存储驱动为 overlay2 了，具体配置步骤如下。

1.停止已经运行的 Docker：

$ sudo systemctl stop docker


2.备份 /var/lib/docker 目录：

$ sudo cp -au /var/lib/docker /var/lib/docker.back


3.在 /etc/docker 目录下创建 daemon.json 文件，如果该文件已经存在，则修改配置为以下内容：

{

  "storage-driver": "overlay2",

  "storage-opts": [

    "overlay2.size=20G",

    "overlay2.override_kernel_check=true"

  ]

}


其中 storage-driver 参数指定使用 overlay2 文件驱动，overlay2.size 参数表示限制每个容器根目录大小为 20G。限制每个容器的磁盘空间大小是通过 xfs 的 pquota 特性实现，overlay2.size 可以根据不同的生产环境来设置这个值的大小。我推荐你在生产环境中开启此参数，防止某个容器写入文件过大，导致整个 Docker 目录空间溢出。

4.启动 Docker：

$ sudo systemctl start docker


5.检查配置是否生效：

$ docker info

Client:

 Debug Mode: false

Server:

 Containers: 1

  Running: 0

  Paused: 0

  Stopped: 1

 Images: 1

 Server Version: 19.03.12

 Storage Driver: overlay2

  Backing Filesystem: xfs

  Supports d_type: true

  Native Overlay Diff: true

 Logging Driver: json-file

 Cgroup Driver: cgroupfs

 ... 省略部分无用输出


可以看到 Storage Driver 已经变为 overlay2，并且 d_type 也是 true。至此，你的 Docker 已经配置完成。下面我们看下 overlay2 是如何工作的。

overlay2 工作原理

overlay2 是如何存储文件的？

overlay2 和 AUFS 类似，它将所有目录称之为层（layer），overlay2 的目录是镜像和容器分层的基础，而把这些层统一展现到同一的目录下的过程称为联合挂载（union mount）。overlay2 把目录的下一层叫作lowerdir，上一层叫作upperdir，联合挂载后的结果叫作merged。


overlay2 文件系统最多支持 128 个层数叠加，也就是说你的 Dockerfile 最多只能写 128 行，不过这在日常使用中足够了。


下面我们通过拉取一个 Ubuntu 操作系统的镜像来看下 overlay2 是如何存放镜像文件的。

首先，我们通过以下命令拉取 Ubuntu 镜像：

$ docker pull ubuntu:16.04

16.04: Pulling from library/ubuntu

8e097b52bfb8: Pull complete

a613a9b4553c: Pull complete

acc000f01536: Pull complete

73eef93b7466: Pull complete

Digest: sha256:3dd44f7ca10f07f86add9d0dc611998a1641f501833692a2651c96defe8db940

Status: Downloaded newer image for ubuntu:16.04

docker.io/library/ubuntu:16.04


可以看到镜像一共被分为四层拉取，拉取完镜像后我们查看一下 overlay2 的目录：

$ sudo ls -l /var/lib/docker/overlay2/

total 0

drwx------. 3 root root      47 Sep 13 08:16 01946de89606800dac8530e3480b32be9d7c66b493a1cdf558df52d7a1476d4a

drwx------. 4 root root      55 Sep 13 08:16 0849daa41598a333101f6a411755907d182a7fcef780c7f048f15d335b774deb

drwx------. 4 root root      72 Sep 13 08:16 94222a2fa3b2405cb00459285dd0d0ba7e6936d9b693ed18fbb0d08b93dc272f

drwx------. 4 root root      72 Sep 13 08:16 9d392cf38f245d37699bdd7672daaaa76a7d702083694fa8be380087bda5e396

brw-------. 1 root root 253, 17 Sep 13 08:14 backingFsBlockDev

drwx------. 2 root root     142 Sep 13 08:16 l


可以看到 overlay2 目录下出现了四个镜像层目录和一个l目录，我们首先来查看一下l目录的内容：

$ sudo ls -l /var/lib/docker/overlay2/l

total 0

lrwxrwxrwx. 1 root root 72 Sep 13 08:16 FWGSYEA56RNMS53EUCKEQIKVLQ -> ../9d392cf38f245d37699bdd7672daaaa76a7d702083694fa8be380087bda5e396/diff

lrwxrwxrwx. 1 root root 72 Sep 13 08:16 RNN2FM3YISKADNAZFRONVNWTIS -> ../0849daa41598a333101f6a411755907d182a7fcef780c7f048f15d335b774deb/diff

lrwxrwxrwx. 1 root root 72 Sep 13 08:16 SHAQ5GYA3UZLJJVEGXEZM34KEE -> ../01946de89606800dac8530e3480b32be9d7c66b493a1cdf558df52d7a1476d4a/diff

lrwxrwxrwx. 1 root root 72 Sep 13 08:16 VQSNH735KNX4YK2TCMBAJRFTGT -> ../94222a2fa3b2405cb00459285dd0d0ba7e6936d9b693ed18fbb0d08b93dc272f/diff


可以看到l目录是一堆软连接，把一些较短的随机串软连到镜像层的 diff 文件夹下，这样做是为了避免达到mount命令参数的长度限制。
下面我们查看任意一个镜像层下的文件内容：

$ sudo ls -l /var/lib/docker/overlay2/0849daa41598a333101f6a411755907d182a7fcef780c7f048f15d335b774deb/

total 8

drwxr-xr-x. 3 root root 17 Sep 13 08:16 diff

-rw-r--r--. 1 root root 26 Sep 13 08:16 link

-rw-r--r--. 1 root root 86 Sep 13 08:16 lower

drwx------. 2 root root  6 Sep 13 08:16 work


镜像层的 link 文件内容为该镜像层的短 ID，diff 文件夹为该镜像层的改动内容，lower 文件为该层的所有父层镜像的短 ID。
我们可以通过docker image inspect命令来查看某个镜像的层级关系，例如我想查看刚刚下载的 Ubuntu 镜像之间的层级关系，可以使用以下命令：

$ docker image inspect ubuntu:16.04

...省略部分输出

"GraphDriver": {

            "Data": {

                "LowerDir": "/var/lib/docker/overlay2/9d392cf38f245d37699bdd7672daaaa76a7d702083694fa8be380087bda5e396/diff:/var/lib/docker/overlay2/94222a2fa3b2405cb00459285dd0d0ba7e6936d9b693ed18fbb0d08b93dc272f/diff:/var/lib/docker/overlay2/01946de89606800dac8530e3480b32be9d7c66b493a1cdf558df52d7a1476d4a/diff",

                "MergedDir": "/var/lib/docker/overlay2/0849daa41598a333101f6a411755907d182a7fcef780c7f048f15d335b774deb/merged",

                "UpperDir": "/var/lib/docker/overlay2/0849daa41598a333101f6a411755907d182a7fcef780c7f048f15d335b774deb/diff",

                "WorkDir": "/var/lib/docker/overlay2/0849daa41598a333101f6a411755907d182a7fcef780c7f048f15d335b774deb/work"

            },

            "Name": "overlay2"

        },

...省略部分输出


其中 MergedDir 代表当前镜像层在 overlay2 存储下的目录，LowerDir 代表当前镜像的父层关系，使用冒号分隔，冒号最后代表该镜像的最底层。

下面我们将镜像运行起来成为容器：

$ docker run --name=ubuntu -d ubuntu:16.04 sleep 3600


我们使用docker inspect命令来查看一下容器的工作目录：

$ docker inspect ubuntu

...省略部分输出

 "GraphDriver": {

            "Data": {

                "LowerDir": "/var/lib/docker/overlay2/4753c2aa5bdb20c97cddd6978ee3b1d07ef149e3cc2bbdbd4d11da60685fe9b2-init/diff:/var/lib/docker/overlay2/0849daa41598a333101f6a411755907d182a7fcef780c7f048f15d335b774deb/diff:/var/lib/docker/overlay2/9d392cf38f245d37699bdd7672daaaa76a7d702083694fa8be380087bda5e396/diff:/var/lib/docker/overlay2/94222a2fa3b2405cb00459285dd0d0ba7e6936d9b693ed18fbb0d08b93dc272f/diff:/var/lib/docker/overlay2/01946de89606800dac8530e3480b32be9d7c66b493a1cdf558df52d7a1476d4a/diff",

                "MergedDir": "/var/lib/docker/overlay2/4753c2aa5bdb20c97cddd6978ee3b1d07ef149e3cc2bbdbd4d11da60685fe9b2/merged",

                "UpperDir": "/var/lib/docker/overlay2/4753c2aa5bdb20c97cddd6978ee3b1d07ef149e3cc2bbdbd4d11da60685fe9b2/diff",

                "WorkDir": "/var/lib/docker/overlay2/4753c2aa5bdb20c97cddd6978ee3b1d07ef149e3cc2bbdbd4d11da60685fe9b2/work"

            },

            "Name": "overlay2"

        },

...省略部分输出


MergedDir 后面的内容即为容器层的工作目录，LowerDir 为容器所依赖的镜像层目录。 然后我们查看下 overlay2 目录下的内容：

$ sudo ls -l /var/lib/docker/overlay2/

total 0

drwx------. 3 root root      47 Sep 13 08:16 01946de89606800dac8530e3480b32be9d7c66b493a1cdf558df52d7a1476d4a

drwx------. 4 root root      72 Sep 13 08:47 0849daa41598a333101f6a411755907d182a7fcef780c7f048f15d335b774deb

drwx------. 5 root root      69 Sep 13 08:47 4753c2aa5bdb20c97cddd6978ee3b1d07ef149e3cc2bbdbd4d11da60685fe9b2

drwx------. 4 root root      72 Sep 13 08:47 4753c2aa5bdb20c97cddd6978ee3b1d07ef149e3cc2bbdbd4d11da60685fe9b2-init

drwx------. 4 root root      72 Sep 13 08:16 94222a2fa3b2405cb00459285dd0d0ba7e6936d9b693ed18fbb0d08b93dc272f

drwx------. 4 root root      72 Sep 13 08:16 9d392cf38f245d37699bdd7672daaaa76a7d702083694fa8be380087bda5e396

brw-------. 1 root root 253, 17 Sep 13 08:14 backingFsBlockDev

drwx------. 2 root root     210 Sep 13 08:47 l


可以看到 overlay2 目录下增加了容器层相关的目录，我们再来查看一下容器层下的内容：

$ sudo ls -l /var/lib/docker/overlay2/4753c2aa5bdb20c97cddd6978ee3b1d07ef149e3cc2bbdbd4d11da60685fe9b2

total 8

drwxr-xr-x. 2 root root   6 Sep 13 08:47 diff

-rw-r--r--. 1 root root  26 Sep 13 08:47 link

-rw-r--r--. 1 root root 144 Sep 13 08:47 lower

drwxr-xr-x. 1 root root   6 Sep 13 08:47 merged

drwx------. 3 root root  18 Sep 13 08:47 work


link 和 lower 文件与镜像层的功能一致，**link 文件内容为该容器层的短 ID，lower 文件为该层的所有父层镜像的短 ID 。diff 目录为容器的读写层，容器内修改的文件都会在 diff 中出现，merged 目录为分层文件联合挂载后的结果，也是容器内的工作目录。**

总体来说，overlay2 是这样储存文件的：overlay2将镜像层和容器层都放在单独的目录，并且有唯一 ID，每一层仅存储发生变化的文件，最终使用联合挂载技术将容器层和镜像层的所有文件统一挂载到容器中，使得容器中看到完整的系统文件。

overlay2 如何读取、修改文件？

overlay2 的工作过程中对文件的操作分为读取文件和修改文件。

读取文件

容器内进程读取文件分为以下三种情况。


文件在容器层中存在：当文件存在于容器层并且不存在于镜像层时，直接从容器层读取文件；
当文件在容器层中不存在：当容器中的进程需要读取某个文件时，如果容器层中不存在该文件，则从镜像层查找该文件，然后读取文件内容；
文件既存在于镜像层，又存在于容器层：当我们读取的文件既存在于镜像层，又存在于容器层时，将会从容器层读取该文件。


修改文件或目录

overlay2 对文件的修改采用的是写时复制的工作机制，这种工作机制可以最大程度节省存储空间。具体的文件操作机制如下。


第一次修改文件：当我们第一次在容器中修改某个文件时，overlay2 会触发写时复制操作，overlay2 首先从镜像层复制文件到容器层，然后在容器层执行对应的文件修改操作。



overlay2 写时复制的操作将会复制整个文件，如果文件过大，将会大大降低文件系统的性能，因此当我们有大量文件需要被修改时，overlay2 可能会出现明显的延迟。好在，写时复制操作只在第一次修改文件时触发，对日常使用没有太大影响。



删除文件或目录：当文件或目录被删除时，overlay2 并不会真正从镜像中删除它，因为镜像层是只读的，overlay2 会创建一个特殊的文件或目录，这种特殊的文件或目录会阻止容器的访问。


结语

overlay2 目前已经是 Docker 官方推荐的文件系统了，也是目前安装 Docker 时默认的文件系统，因为 overlay2 在生产环境中不仅有着较高的性能，它的稳定性也极其突出。但是 overlay2 的使用还是有一些限制条件的，例如要求 Docker 版本必须高于 17.06.02，内核版本必须高于 4.0 等。因此，在生产环境中，如果你的环境满足使用 overlay2 的条件，请尽量使用 overlay2 作为 Docker 的联合文件系统。

那么你知道除了我介绍的这三种联合文件系统外，Docker 还可以使用哪些联合文件系统吗？ 思考后，可以把你的想法写在留言区。

                        
                        
                            