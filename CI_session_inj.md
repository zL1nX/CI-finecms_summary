#CI框架中的session加密漏洞
- [文章链接][]
[文章链接]:http://wps2015.org/drops/drops/Codeigniter%20%E5%88%A9%E7%94%A8%E5%8A%A0%E5%AF%86Key%EF%BC%88%E5%AF%86%E9%92%A5%EF%BC%89%E7%9A%84%E5%AF%B9%E8%B1%A1%E6%B3%A8%E5%85%A5%E6%BC%8F%E6%B4%9E.html
- 结果:应该是成功了
- 实验环境
    - CI：2.1
    - phpstudy
    - phpstorm

##0x00 CI中的session加密机制
- 通过阅读文章我们可以大致知道CI对一个用户的session做了如下事情
    - CI中的session是存在客户端的cookie中，而非存在服务器里
    - session类中的构造函数先进行了session的读取，函数是*sess_read*
    - session类中还有另一种函数进行session的生成，函数是*sess_create*
    - CI框架对session会进行序列化反序列化的操作
    - 加密机制里我们在salt已知的情况下可以进行cookie的欺骗，使之对CI中的特定库文件进行任意代码的写入
- sess_read函数
![sess_read](C:/张效林/一些资料/php框架/CI/sess_read.png)
    - 部分代码如图，这里的代码是对已经处理的session进行验证，即进行hash值的验证，检验其是否和生成的方式相同（这里生成方式的内容在sess_create中），去检验原本的session值是否被篡改
    - 这里是对验证过的session进行反序列化处理`$session = $this->_unserialize($session)`
    - 再进行一系列的匹配比如用户ip，代理等之后如果均通过验证，就返回true。
- sess_create函数
![sess_create](C:/张效林/一些资料/php框架/CI/sess_create.png)
    - 代码如图，这里的代码是生成session里面最重要的函数即*set_cookie*
    - 其中，最重要的一句就是`$cookie_data = $cookie_data.md5($cookie_data.$this->encryption_key)`这句代码代表了cookiede产生方式，也就是实际的cookie值与一个加密用的key即salt，二者拼接之后md5得到的一串值，再将这串md5与先前的cookie值进行拼接。这样就得到了我们的cookie

##0x01 漏洞分析
- 阅读文章之后，我们知道对于一个用CI编写的应用来说，当给页面传一个cookie时，对应生成的session是会存在客户端。也就是说，CI并没有用真正的session，而是将session变量存入了cookie中。
- CI调用序列化反序列函数来处理session变量
- CI按照次序从类中创建对象，CI会查看`$autoload['libraries']`数组进行次序创建对象，因此初始化session类的路径就很重要。

##0x02 漏洞利用
- [example][]
[example]:https://github.com/mmetince/codeigniter-object-inj
- 已知了salt为h4ck3rk3y
- 具体payload请见`CI_session_file.md`
- 过程实现
    - 首先我们的目的是要通过我们伪造的一个cookie，传入到CI内，通过CI对session的处理即对cookie的处理之后，进行了对这个伪造cookie的处理，就可以进行对象的注入。
    - 我们要注入到哪里呢？通过CI的处理顺序以及具体结构，我们发现这样一个类文件Customecacheclass,这个文件的具体作用就是将这个类变量保存到了cache.php文件中，假如我们的伪造的cookie中包含了这个类变量的具体内容，这样一来，在CI加载session类的时候会提前加载library中的类，于是我们的cookie就被顺利注入了，后面的session检查也不会有什么问题。
    - 因此我们构造payload之后，将序列化后的输出与salt拼接，在进行md5，在进行原变量的拼接，最后urlencode，得到我们最终cookie值。至于我们的payload的类文件中，value属性我们可以设为我们需要注入的php代码，dir属性可以设置为我们需要注入的文件（正是因为这个类最后将变量输入到另一个文件中，所以我们要利用这个类）。
    - 在进行完完整的payload的处理之后，这里使用postman插件进行cookie的传入，传入之后可以发现cache.php里出现了我们的代码。
    - 进行到这一步我们可以得出结论就是我们的cookie已经成功被session类处理并且注入了，那么当代码是一句话木马时，这就相当于为我们留下了shell（后来用菜刀不知道为什么连接不了。。。）

##0x03 一点总结
- 感觉这个漏洞需要很大的灵性，一是你得知道salt，当然暴力破解也是可行的不过这就很费力了。
- 这个类文件是我们开发过程中会留下的，而且感觉要求很高，需要有对其他文件进行读写的操作。而且这个library类库是刚好在session类加载之前就会执行的，所以我们能进行注入。
- 总之，感觉有点迷，可能是因为我太菜了吧