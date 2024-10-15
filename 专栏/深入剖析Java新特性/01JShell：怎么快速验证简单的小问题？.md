
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            01 JShell：怎么快速验证简单的小问题？
                            你好，我是范学雷。今天，我们聊一聊Java的交互式编程环境，JShell。

JShell这个特性，是在JDK 9正式发布的。从名字我们就能想到，JShell是Java的脚本语言。一门编程语言，为什么还需要支持脚本语言呢？编程语言的脚本语言，会是什么样子的？它又能够给我们带来什么帮助呢？

让我们一起来一层一层地拆解这些问题，弄清楚Java语言的脚本工具是怎么帮助我们提高生产效率的。我们先从阅读案例开始。

阅读案例

学习编程语言的时候，我们可能都是从打印“Hello, world!”这个简单的例子开始的。一般来说，Java语言的教科书也是这样的。今天，我们也从这个例子开始，温习一下Java语言第一课里面涉及的知识。

class HelloWorld {
    public static void main(String[] args) {
        System.out.println("Hello, world!");
    }
}


好了，有了这段可以拷贝的代码，接下来我们该怎么办呢？

首先，我们需要一个文本编辑器，比如vi或者类似于IDEA这样的集成编辑环境，把这段代码记录下来。文本编辑器，每个人都有不同的偏好，每个系统都有不同的偏好。一个软件工程师，可能需要很长时间，才能找到自己顺手的编辑器。就我自己而言，我使用了二十多年vi编辑器，直到这两年才发现IDEA的好。但是使用IDEA的时候，我还是会不自主地敲击vi的命令。不得不说，顺手，确实是一个很顽固、难改的行为习惯。

回到刚才的正题。有了文本编辑器，接下来，我们要把这段源代码编译成Java的字节码。编译器会帮助我们评估这段代码，看看有没有错误，有没有需要警示的地方。通常，我们使用javac命令行，或者通过集成编辑环境自动编译。

$ javac HelloWorld.java


编译完成之后，我们要运行编译好的字节码，把程序的结果显示出来。在这里，我们一般使用java命令行，或者通过集成编辑环境来运行。

$ java HelloWorld


最后一步，我们要观察运行的结果，检查一下是不是我们期望的结果。

Hello, world!


如果让我去教授Java语言，教到这里，我会让同学们小小地庆祝一下：我们完成了Java语言的第一个程序。

万事开头难，完成Java语言的第一个小程序，尤其难！ 你要学习使用编辑器、使用编译器、使用运行环境。对于一个编程语言的初学者而言，这是迈入Java语言世界的第一步，也是很大的一步。这当然是巨大的收获，一个小小的庆祝当然是应得的也是值得的！

当然，会有同学试着改动这段代码，享受创造的乐趣。比如说，把“Hello, world!”改成“世界你好”或者“How are you?”。 这样一来，我们就还要经历编辑、编译、运行、观察这样的过程。

class HowAreYou {
    public static void main(String[] args) {
        System.out.println("How are you?");
    }
}


毫不意外，对Java的了解更深之后，还会有同学继续修改代码，把System.out换成System.err。然后，同样的过程还要再来一遍：编辑、编译、运行、观察。

其实，编辑、编译、运行、观察这四个步骤，就是我们学习一门新语言或者一项新特性的常规过程。如果你已经有多年的Java语言使用经验，想一想吧，你是怎么学习JDK 7的try-with-resource语句，又是怎么学习JDK 8的Lambda表达式的？是不是也是类似的过程？

也许，你已经习惯了这样的过程，并没有感觉得到有什么不妥当的地方。不过，如果我们看看bash脚本语言的处理，也许你会发现问题所在。

bash $ echo Hello，World！
Hello, world!
bash $


显然，使用bash编写的“Hello, world!”要简单得多。你只需要在命令行输入代码，bash就会自动检查语法，立即打印出结果；它不需要我们调用额外的编辑器、编译器以及解释器。当然，这并不是说bash不需要编译和运行过程。bash只是把这些过程处理得自动化了，不再需要我们手动处理了。

拖后腿的学习效率

没有对比，就没有伤害。一般来说，不管是初学者还是熟练的程序员，使用bash都可以快速编写出“Hello, world!”，不到一分钟，我们就可以观察到结果了。但是如果使用Java，一个初学者，也许需要半个小时甚至半天才能看到输出结果；而一个熟练的程序员，也需要几分钟甚至十几分钟才能完成整个过程。

这样的学习效率差异并不是无关紧要的。有来自学校的反馈表明，老师和学生放弃Java的最重要的原因，就是学习Java的门槛太高了，尤其是入门第一课。上面的这个小小的“Hello, world!”程序，需要极大的耐心，才能看到最后的结果。这当然影响了新的小伙伴们学习Java的热情。而且，老朋友们学习Java新技术的热情，以及深入学习现有技术的热情，也会受到了极大的阻碍。

JDK 17发布的时候，我们经常可以看到这样的评论，“然而，我还是在使用JDK 8”。确实，没有任何人，也没有任何理由责怪这样的用户。除非有着严格的自律和强烈的好奇心，没有人喜欢学习新东西，尤其是学习门槛比较高的时候。

如果需要半个小时，我们才能看一眼一个新特性的样子，重点是，这个新特性还不一定能对我们有帮助，那很可能我们就懒得去看了。或者，我们也就是看一眼介绍新特性的文档，很难有动手试一试的冲动。最后，我们对它的了解也就仅仅停留在“听过”或者“看过”的程度上，而不是进展到“练过”或者“用过”的程度。

那你试想一下，如果仅仅需要一分钟，我们就能看到一个新特性的样子呢？我想，在稍纵即逝的好奇心消逝之前，我们很有可能会尝试着动动手，看一看探索的成果。

实际上，学习新东西，及时的反馈能够给我们极大的激励，推动着我们深入地探索下去。那Java有没有办法，变得像bash那样，一分钟内就可以展示学习、探索的成果呢？

及时反馈的JShell

办法是有的。JShell，也就是Java的交互式编程环境，是Java语言给出的其中一个答案。

JShell API和工具提供了一种在 JShell 状态下交互式评估 Java 编程语言的声明、语句和表达式的方法。JShell 的状态包括不断发展的代码和执行状态。为了便于快速调查和编码，语句和表达式不需要出现在方法中，变量和方法也不需要出现在类中。

我们还是通过例子来理解上面的表述。

启动JShell

JShell的工具，是以Java命令的形式出现的。要想启动JShell的交互式编程环境，在控制台shell的命令行中输入Java的脚本语言命令 “ jshell ” 就可以了。

下面的这个例子，显示的就是启动JShell这个命令，以及JShell的反馈结果。

$ jshell
|  Welcome to JShell -- Version 17
|  For an introduction type: /help intro

jshell>


我们可以看到，JShell启动后，Java的脚本语言就接管了原来的控制台。这时候，我们就可以使用JShell的各种功能了。

另外，JShell的交互式编程环境，还有一个详细模式，能够提供更多的反馈结果。启用这个详尽模式的办法，就是使用“-v”这个命令行参数。我们使用JShell工具的主要目的之一，就是观察评估我们编写的代码片段。因此，我一般倾向于启用详细模式。这样，我就能够观察到更多的细节，有助于我更深入地了解我写的代码片段。

$ jshell -v
|  Welcome to JShell -- Version 17
|  For an introduction type: /help intro


退出JShell

JShell启动后，就接管了原来的控制台。要想重新返回原来的控制台，我们就要退出JShell。退出JShell，需要使用JShell的命令行。

下面的这个例子，显示的就是怎么使用JShell的命令行，也就是“exit”，退出java的交互式编程环境。需要注意的是，JShell的命令行是以斜杠开头的。

jshell> /exit
|  Goodbye


JShell的命令

除了退出命令，我们还可以使用帮助命令，来查看JShell支持的命令。比如，在JDK 17里，帮助命令的显示结果，其中的几行大致是下面这样：

jshell> /help
|  Type a Java language expression, statement, or declaration.
|  Or type one of the following commands:
|  /list [<name or id>|-all|-start]
|  	list the source you have typed
... snipped ...
|  /help [<command>|<subject>]
|  	get information about using the jshell tool
... snipped ...


熟悉JShell支持的命令，能给我们带来很大的便利。限于篇幅，我们这里不讨论JShell支持的命令。但是，我希望你可以通过帮助命令，或者其他的文档，了解这些命令。它们可以帮助你更有效率地使用这个工具。

我相信你肯定会对帮助命令显示的第一句话非常感兴趣：输入Java语言的表达式、语句或者声明。下面我们就来重点了解一下这一部分。

立即执行的语句

首先，我们来看一看使用JShell来评估Java语言的语句。比如，我们可以使用JShell来完成打印“Hello, world!”这个例子。

jshell> System.out.println("Hello, world!");
Hello, world!

jshell>


可以看到，一旦输入完成，JShell立即就能返回执行的结果，而不再需要编辑器、编译器、解释器。

更方便的是，我们可以使用键盘的上移箭头，编辑上一次或者更前面的内容。如果我们想评估System.out其他的方法，比如不追加行的打印，我们编辑上一次的输入命令，把上面例子中的“println”换成“print”。就像下面这样就可以了。

jshell> System.out.print("Hello, world!");
Hello, world!
jshell>


如果我们使用了错误的方法，或者不合法的语法，JShell也能立即给出提示。

jshell> System.out.println("Hello, world\!");
|  Error:
|  illegal escape character
|  System.out.println("Hello, world\!");
|                                   ^


JShell的这种立即执行、及时反馈的特点，毫无疑问地，加快了我们学习和评估简单Java代码的速度，激励着我们去学习更多的东西，更深入的技能。

可覆盖的声明

另外，JShell还有一个特别好用的功能。那就是，它支持变量的重复声明。JShell是一个有状态的工具，这样我们就能够很方便地处理多个有关联的语句了。比如说，我们可以先试用一个变量来指代问候语，然后再使用标准输出打印出问候语。

jshell> String greeting;
greeting ==> null
|  created variable greeting : String

jshell> String language = "English";
language ==> "English"
|  created variable language : String

jshell> greeting = switch (language) {
   ...>     case "English" -> "Hello";
   ...>     case "Spanish" -> "Hola";
   ...>     case "Chinese" -> "Nihao";
   ...>     default -> throw new RuntimeException("Unsupported language");
   ...> };
greeting ==> "Hello"
|  assigned to greeting : String

jshell> System.out.println(greeting);
Hello

jshell> 


为了更方便地评估，你可以使用JShell运行变量的重复声明和类型变更。比如说，我们可以再次声明只带问候语的变量。

jshell> String greeting = "Hola";
greeting ==> "Hola"
|  modified variable greeting : String
|    update overwrote variable greeting : String


或者，把这个变量声明成一个其他的类型，以便后续的代码使用。

jshell> Integer greeting;
greeting ==> null
|  replaced variable greeting : Integer
|    update overwrote variable greeting : String


变量的声明可以重复，也可以转换类型，就像上一个声明并不存在一样。这样的特点和Java的可编译代码有所不同，在可编译的代码里，在一个变量的作用域内，这个变量的类型是不允许转变的，也不允许重复声明。

JShell支持可覆盖的变量，主要是为了简化代码评估，解放我们的大脑。要不然，我们还得记住以前输入的、声明的变量，这可不是一个简单的任务。

也正是因为JShell支持可覆盖的变量，我们才能说JShell支持不断发展的代码，JShell才能够更有效地处理多个关联的语句。

独白的表达式

前面我们说过，JShell工具可以接受的输入包括Java语言的表达式、语句或者声明。刚才讨论了语句和声明的例子，现在我们来看看输入表达式是什么样子的。

我们知道，在Java程序里，语句是最小的可执行单位，表达式并不能单独存在。但是，JShell却支持表达式的输入。比如说，输入“1+1”，JShell会直接给出正确的结果。

jshell> 1 + 1
$1 ==> 2
|  created scratch variable $1 : int


有了独立的表达式，我们就可以直接评估表达式，而不再需要把它附着在一个语句上了。毫无疑问，这简化了表达式的评估工作，使得我们可以更快地评估表达式。下面的例子，就可以用来探索字符串常量和字符串实例的联系和区别，而不需要复杂的解释性代码。

jshell> "Hello, world" == "Hello, world"
$2 ==> true
|  created scratch variable $2 : boolean

jshell> "Hello, world" == new String("Hello, world")
$3 ==> false
|  created scratch variable $3 : boolean


总结

好，到这里，今天的课程就要结束了，我来做个小结。从前面的讨论中，我们了解了JShell的基本概念、它的表达形式以及编译的过程。

JShell提供了一种在 JShell 状态下交互式评估 Java 编程语言的声明、语句和表达式的方法。JShell 的状态包括不断发展的代码和执行状态。为了便于快速调查和编码，语句和表达式不需要出现在方法中，变量和方法也不需要出现在类中。

JShell的设计并不是为了取代IDE。JShell在处理简单的小逻辑，验证简单的小问题时，比IDE更有效率。如果我们能够在有限的几行代码中，把要验证的问题表达清楚，JShell就能够快速地给出计算的结果。这一点，能够极大地提高我们的工作效率和学习热情。

但是，对于复杂逻辑的验证，使用JShell也许不是一个最优选择。这时候，也许使用IDE或者可编译的代码更合适。

我还拎出了几个技术要点，这些都可能在你的面试中出现。通过这一次学习，你应该能够：


了解JShell的基本概念，知道JShell有交互式工具，也有API；


面试问题：你使用过JShell吗？

知道JShell能够接收Java编程语言的声明、语句和表达式，以及命令行；


面试问题：JShell的代码和普通的可编译代码，有什么不一样？



这一次的讨论，主要是想让你认识到JShell能给我们带来的便利，知道简单的使用方法。这样，当后面我们想要讨论更多的话题时，你就可以使用JShell快速验证你的小问题、小想法。 要想掌握JShell更复杂的用法，请参考相关的文档或者材料。

思考题

在前面的讨论里，我们使用了一个例子，来说明Java处理字符串常量的方式。

jshell> "Hello, world" == "Hello, world"
$2 ==> true
|  created scratch variable $2 : boolean


对于精通Java语言的同学，这个例子也许是直观的。但对部分同学来说，这个例子也许过于隐晦。过于隐晦的代码不是好的代码。同样地，过于隐晦的JShell片段也不是好的片段。

你有没有办法，让这个例子更容易理解？使用多个JShell片段，是不是更好理解？这就是我们今天的思考题。

欢迎你在留言区留言、讨论，分享你的阅读体验以及你对这个思考题的处理办法。

注：本文使用的完整的代码可以从GitHub下载，你可以通过修改GitHub上review template代码，完成这次的思考题。如果你想要分享你的修改或者想听听评审的意见，请提交一个 GitHub的拉取请求（Pull Request），并把拉取请求的地址贴到留言里。这一小节的拉取请求代码，请放在实例匹配专用的代码评审目录下，建一个以你的名字命名的子目录，代码放到你专有的子目录里。比如，我的代码，就放在jshell/review/xuelei的目录下面。

                        
                        
                            