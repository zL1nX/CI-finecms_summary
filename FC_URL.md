#finecms 一些漏洞
##链接：[LoRexxar](https://lorexxar.cn/2017/07/26/finecms%E5%88%86%E6%9E%90/)
##URL跳转漏洞
- 危害：对服务器不会造成很大破坏，但是对安全意识不高的用户，可以利用url跳转到钓鱼页面从而诱骗用户
- 简介：对url参数没有过滤而直接进行跳转
- 详情
    - 漏洞位置：`./finecms/dayrui/controllers/Weixin.php`的sync方法
    ![sync](C:/张效林/一些资料/php框架/CI/FC_URL.png)
    - 分析：从代码中可以看到如果uid存在，直接进行url的跳转，也就是说任意用户只要登录，执行这个方法脚本都会无条件进行页面的重定向
    - payload：`http://127.0.0.1/index.php?c=weixin&m=sync&url=http://www.baidu.com`
    - 结果：成功跳转到百度页面
    ![result](C:/张效林/一些资料/php框架/CI/FC_URL_RES.png)
    - 一些说明：url中的参数只能是符合基本协议的url，参见redirect函数的内容，可以发现这个函数对传入的url进行了一个基本的判断，使之确保符合url的基本格式。这个漏洞原理比较简单，而且危害也不大，但是令人费解的是项目中并没有调用sync这个方法的地方，所以该如何调用呢？另外，这个漏洞中url是我们主动手打上去的，然而实际情况中，用户是不可能通过get方法提交一串钓鱼网址的，因此不知道这个漏洞的触发情景是怎样的（也许是我太菜了）。我认为只以我们个人攻击者的角度出发，一串payload是没有太大实际意义的，必须要有外部触发的参与才行。

##前台post导致的反射型xss
- 危害：一般反射型xss的危害，盗取数据，插入木马，getshell等。
- 简介：由于CI框架中对post参数的过滤补全导致的前台反射型xss
- 详情：
    - 漏洞位置：`./finecms/system/core/Security.php`
    ![xss_clean](C:/张效林/一些资料/php框架/CI/FC_XSS_CLEAN.png)
    ![login](C:/张效林/一些资料/php框架/CI/FC_LOGIN.png)
    - login分析：实际上这个漏洞应该算作CI框架的一个漏洞，在finecms中的后台登录页面即admin.php中调用了这个含有缺陷的Login方法。为什么说有缺陷呢？原因有二：首先因为login函数使用了CI的post方法，然而这个方法本身就是有缺陷的，至于缺陷在哪里先按下不表；其次，login方法首先做了一个查询观察输入的username&password是否正确，后来这个方法又把带有不正确（or正确）的用户名返回到页面中去，正如display方法所示，因此这个页面势必会带有一个带有用户输入的经过CI过滤过的参数，假如我们能够绕过这些过滤，那么是不是可以就把我们的恶意代码有带到页面中去了呢？显然是这样的。这个方法的缺陷就是这样。
    - xss_clean分析：上面提到，这个漏洞的本质还是由于CI中post方法对于xss的过滤不严谨而导致的，那么看一下post类中的xss_clean方法，为什么要看这个方法呢？因为我们通过login方法的源码知道，post里面第二个参数*true*是用来“唤醒”xss过滤的一个标志，一般情况下这个xss过滤的样子是`post(your_var,$xss_clean=NULL)`，这样一来当为*true*时，就会调用这个函数进行过滤。真正的问题出在了一个循环中，这个循环将输入的字符串进行正则匹配，问题就在于正则匹配的模式太死板了，可以看到`preg_match()`里面的正则模板，只考虑了恶意代码的开头是尖括号的形式，而没有考虑到如果是利用DOM来xss是不一定需要尖括号的，而且通过测试也可以发现并没有对双引号进行过滤，那么我们就可以通过闭合双引号构造到DOM里去，形成一个反射型xss。利用如下。
    - 结果利用：成功插入恶意的用户名代码，显示弹窗,payload为`1" onmouseover=alert(1) a="1`
    ![xss_result](C:/张效林/一些资料/php框架/CI/FC_XSS_RES.png)
    - 一些说明：这个前台漏洞的确感觉原理并不难，可是自己实际上手去挖掘就比较困难了，因为这需要对CI的源码结构，cms的开发的结构都有一个十分清晰的认识，像这个漏洞里面，虽然表面上看起来是finecms的锅，其实深究进去甚至到了最里层的post的方法的参数的定义里面去，才发现这是CI框架的不合理的地方，只是由于finecms不合时宜的调用了出来，才导致的漏洞的触发。同时，也确实又认识到了DOMxss的利用，有时候对payload不理解f12一下就明白了。同理可以知道，因为这是CI框架本身的不合理，所以只要基于CI的cms中有类似的将参数再次回传给页面的行为，都可以留心判定是否能触发xss。

##前台无限制反射型xss
- 危害：同上
- 简介：由于函数内接受参数未过滤而且又将参数输出到页面导致的反射型xss
- 详情：
    - 漏洞位置：`./finecms5.0.0/finecms/dayrui/controllers/Api.php`版本为5.0.0（5.2.0搁置了该方法，漏洞函数为空。。。）
    ![xss_function](C:/张效林/一些资料/php框架/CI/FC_XSS_FUNC.png)
    - 分析：这个漏洞的原理比较简单，通过上面的代码逻辑可以知道，在获得format和function两参数之后，如果这个function不存在，那么将这个值存入data数组，在之后如果format为php的话，脚本就会将这个data输出到页面，与上一个情景相同，也是重新将用户输入的参数输出到页面中，而这个漏洞是因为在传入format或function参数的时候，在get方法中没有添加*true*参数，也就意味着没有进行xss过滤。所以我们构造的任何有效的xsspayload都可以执行成功。
    - 结果利用：成功通过前台访问特定的参数使恶意代码插入到页面中去，payload为`?c=api&m=data2&function=var_d1<script>alert(1)</script>&format=php`function参数可以为任意的非函数名称的字符串。
    ![xss_result2](C:/张效林/一些资料/php框架/CI/FC_XSS_RES2.png)
    - 一些说明：同样的，这个漏洞可能是大神瞥了一眼就发现的，利用情景和上面的一样，都是将用户的恶意参数输出到页面执行了，然而不同的是，上面的原因是因为*true*参数的xss_clean中没有进行全部的合理的过滤，而且输出位置是在页面的dom中，这样一来就可以构造DOMxss；这个漏洞里是因为根本就没有注意xss过滤而直接提取并输出了。那么这里加上*true*是否可行呢？我个人认为是不行的，因为上面的xss_clean函数我们可以发现里面对尖括号进行了过滤，然而上面的xss可以在DOM中插入，无需尖括号。但是这个地方里的xss的输出页面什么也没有，若想执行JS代码，就必须有脚本的尖括号引入浏览器才能解析，否则即使可以绕过，也是没有意义的。

##使用原生sql语句导致的注入问题
- 危害：getshell，泄露服务器信息等与常规sql注入危害相同
- 简介：由于在文件中的查询位置使用了原生sql语句，而且没有做十分有效的过滤，这样一来就可以构造参数造成sql注入
- 详情：
    - 漏洞位置：`./finecms5.0.0/finecms/dayrui/libraries/Template.php`
    ![template](C:/张效林/一些资料/php框架/CI/FC_SQL1_TEMP.png)
    ![query](C:/张效林/一些资料/php框架/CI/FC_SQL1_QUERY.png)
    - 分析：
        - *param* 参数：这个漏洞的连锁条件比较多，开始时也是data2这个函数的问题，首先是在开始时进行了一个判断，判断*auth*这个参数是否合法，与之比较的是SYS_KEY，然而这个变量我们是可以通过浏览器的查看cookie功能，看到session的前缀就是这个SYS_KEY，那么auth这个参数我们就可以绕过。其次，我们注意到list_tag()函数进行的是sql查询功能，而且将返回值输出到了页面中去，因此这里就可能存在注入的问题。于是我们一路追踪param参数，发现这个参数是外部传入，而且相当于选择器的功能，这里我们也可以先留意一下。进入listtag函数，发现函数中的related选项下，使用了原生的sql进行查询，即上图代码中的where变量。因此我们就可以思考如何将我们所要注入的代码带入呢？容易知道这个函数里的主要变量是system数组，由他进行了一系列查询变量的提供，这里如果我们可以将查询变量和查询方式可控即可，然而查询变量就是我们的catid，可以get传入，查询方式的话由于要利用原生sql的弱点，即*related*这个case下的，这个case由action决定，action不是可控的，然而上面提到我们的param是可控的，而且会被传到list_tag函数中去，因此这里就首先会形成一个变量覆盖的问题，即param参数为`param=action=related`。
        - *catid* 参数：
        ![catid_bypass](C:/张效林/一些资料/php框架/CI/FC_SQL1_BYPASS.png)
        之后我们进入related部分查看，可以发现这里就是进行了where变量的拼接，并且如图进行了查询，然而关于参数的过滤，我们可以看到这部分只有对catid中是否有空格或者逗号进行了过滤，然而在之后的程序内，可以看出catid是参与到where变量的构造之中，也就是说，如果我们能够绕过catid发热过滤，成功将恶意代码插入到where中去那么就可以成功执行sql注入的漏洞。因此，我们现在的目的就是如何构造payload。重新看catid的过滤方式,通过上图的示例，我们发现真正进行过滤的是在两个else情况中，而上面的if里面是进行catid的处理，通过explode，implode函数等，这里我们构造的catid需要有逗号（正如if判断里所述），但是又不能真是出现逗号，这样会让我们构造的payload变得支离破碎，因此这里大神的方法就是利用了[join](http://www.venenof.com/index.php/archives/240/)来进行绕过，同时也不能有空格，这会让payload变得无效，大神这里是利用了换行符即%0a来绕过。因此，这样就可以将我们的sql语句成功插入进去了。
    - 结果 ![sql1_result](C:/张效林/一些资料/php框架/CI/FC_SQL1_RES.png)
    - payload:`c=api&m=data2&auth={md5(SYS_KEY)} &param=action=related%20module=news%20tag=1,2%20catid=1,12))%0aand%0a0%0aunion%0aselect%0a*%0a from(((((((((((((((((((select(user()))a)join(select(2))b)join(select(3))c)join(select(4))d)join(select(5))e)join(select(6))f)join(select(7))g)join(select(8))h)join(select(9))i)join(select(10))j)join(select(11))k)join(select(12))l)join(select(13))m)join(select(14))n)join(select(15))o)join(select(16))p)join(select(17))q)join(select(18))x)%23`
    - 一些说明：这个漏洞的发现确实很厉害，首先的条件就是能够发现cookie头是就syskey，这样才能绕过第一步验证，之后还能够准确判断是在listtag函数内出现的问题，其次还要能通过过滤条件构造出payload，这几步都是需要很丰富的经验才能察觉。私以为从这个漏洞中学到的东西有对cookie，session等变量命名方式的敏感程度，对原生sql语句查询的敏感程度，对之间各个参数来回传递，哪些变，在哪进行的过滤的熟悉程度，以及最后payload的绕过方式，空格用换行符，逗号用join。
    
## 接上一部分原生sql查询导致的limit注入
- 危害，简介等相同故略过
- 这个漏洞的位置如图
![sql2_num](C:/张效林/一些资料/php框架/CI/FC_SQL2_NUM.png)
- 漏洞原理：如代码所示，sql变量中其实是原生的sql查询语句，而且其中的可变变量可以由用户决定，这里出现了*limit*关键字，然而我们发现其之后的num变量恰好就是我们可以定义的，因此这里就是一种比较常见的sql注入漏洞，即limit注入，通过procedure及其他函数的配合将报错信息回显，从而得到敏感的查询信息。具体原理在hacking lab的web部分write up中有写。
- payload `http://127.0.0.1/index.php?c=api&m=data2&auth=74a0c18637d1c7585a37b331c78d71a8&param=action=related catid=1 tag=1,2 num=1/**/PROCUDURE/**/analyse(extractvalue(rand(),concat(0x3a,database())),1)`
- 复现结果：似乎是失败了，从返回的报错信息来看，finecms将url中的斜杠会转义，也就是如下图所示的那样，这样一来我们的payload中的代替空格的*/*1*/*就会被转义，从而使我们后面的limit的注入语句失效，这可能是导致复现失败的原因。同时也尝试了其他payload比如将空格换为%0a等，发现也无效，可能这个版本的fc已经将这个漏洞修复了吧，，
![sql2_res](C:/张效林/一些资料/php框架/CI/FC_SQL2_RES.png)

## 直接拼接原生sql语句而未过滤导致的多入口的sql注入
- 危害：同上，不过漏洞利用的入口更多，更灵活
- 简介：在list_tag()函数内的*switch&case*语句块内，每种情况中的field变量都是直接拼接到原生的SQL语句中，之后未经过过滤直接进行查询，导致了sql注入的产生，而且这种情况在四种case里面都存在，也就意味着可以有多个入口触发漏洞
- 详情：
    - 漏洞位置：
    ![sql3_location](C:/张效林/一些资料/php框架/CI/FC_SQL3_FIELD.png)
    - 分析：跟其他finecms的sql注入的漏洞类似，同样是注意到这里的field变量直接就接入了sql语句进行查询，而且查询语句紧接下面，没有经过任何的过滤处理，这样一来显而易见的就是我们可以通过这里来进行sql注入。这个漏洞的原理也比较简单，注意的就是在构造payload的时候需要将某一case内的必要变量都要构造出来，比如在related里，首先需要有module变量，其次需要有tag变量，最后再接上我们的field变量。构造payload的时候不需要考虑其他的一些奇淫技巧，因为这里是直接接入的，所以我们除了空格之外就直接写一些常见的payload就可以
- 结果：![sql3_res](C:/张效林/一些资料/php框架/CI/FC_SQL3_RES.png)
- payload:`http://127.0.0.1/index.php?c=api&m=data2&auth=74a0c18637d1c7585a37b331c78d71a8&param=action=related%20module=news%20tag=1%20field=1%0Aunion%0aselect%0Adatabase()%23`(related模块)
          `http://127.0.0.1/index.php?c=api&m=data2&auth=74a0c18637d1c7585a37b331c78d71a8&param=action=form%20form=1%20field=1%0Aunion%0Aselect%0Adatabase()%23`(form模块)
          `http://127.0.0.1/index.php?c=api&m=data2&auth=74a0c18637d1c7585a37b331c78d71a8&param=action=memeber%20field=1%0Aunion%0Aselect%0Adatabase()%23`（member模块）
          `http://127.0.0.1/index.php?c=api&m=data2&auth=74a0c18637d1c7585a37b331c78d71a8&param=action=module%20%20module=news%20field=1%0Aunion%0Aselect%0Adatabase()%23`（module模块）

## eval导致恶意代码执行
- 危害：可以执行任意php代码，从而会泄露敏感信息，getshell等
- 简介：在*cache*的case中，由于使用了eval函数从而可以使精心构造的参数绕过过滤，插入到函数中使代码执行
- 详情：
    - 漏洞位置：![eval_cache](C:/张效林/一些资料/php框架/CI/FC_EVAL_CACHE.png)
    - 分析：
        - 首先我们看上面这个cache-case，在将外部传入的name参数进行处理提取之后变成了`$_name&$_param`两个参数，然而这两个参数后来分别以各自的形式都参与到了eval函数的使用中，即这里的eval函数还是调用了外部分变量，虽然经过一些手动和函数的处理，但是也是存在被绕过的可能性的。然后我们追踪到处理$_name函数的_cache_var函数中去。
        - _cache_var函数 ![eval_cache_var](C:/张效林/一些资料/php框架/CI/FC_EVAL_CACHE.png)
         这个函数我们可以看到里面并没有进行任何实质性的过滤，只是做了一个选择的功能，然后将参数返回，因此这部分我们不用考虑绕过，直接按照这函数里的case构造即可。
        - _get_var ![eval_get_var](C:/张效林/一些资料/php框架/CI/FC_EVAL_GETVAR.png)
         这个函数是将param参数进行处理然后插入eval的部分，我们追踪进去看到这个函数对传进来的param参数先进行分割，然后用三个if-else进行判断过滤，foreach函数里进行循环，我们看到在进行处理的变量前后都加上了中括号，然后再进行正则的替换，转义之类的操作。因为我们的目的是要让插入的代码执行，而前后两端加上中括号之后显然是会失效的，因此我们要在构造好的$_param函数前后手动构造上中括号的各半部分，这样一来就可以将过滤的中括号闭合，使我们真正有用的代码逃逸出来。
         - 综合上面分析，我们的payload分为两部分，第一部分就是普通的case参数，比如MEMBER，第二部分就是前后各有半个中括号，中间是真正php代码，这两部分用`.`相连，这样一来我们就可以成功利用第一部分通过判断，利用第二部分及其绕过方式插入eval函数。
    - 结果：![eval_res](C:/张效林/一些资料/php框架/CI/FC_EVAL_GETVAR.png)
    - payload:`http://127.0.0.1/index.php?c=api&m=data2&auth=74a0c18637d1c7585a37b331c78d71a8&param=action=cache%20name=MEMBER.1'];phpinfo();['1`
    - 一些说明：这个漏洞的利用方式比较有趣，因为缺陷函数里的eval部分并不是直接将外部变量加入，而是分割之后再进行，因此我们就需要构造一种两个部分的payload，分为正常部分和插入部分，这样的构造思路有需要参考到一些finecms的底层函数，像上面的闭合中括号的操作与这里的eval执行代码结合起来就比较新颖。而且这个漏洞的原理并不是特别复杂，利用方式比较有趣而且很直观。