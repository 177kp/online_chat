# online_chat服务端
#### 简介
online_chat服务端是用PHP写的在线聊天软件；引入online_chat简单、方便；online_chat服务端结构分成server服务程序和web的api组成；server服务程序用原生PHP写的，也用到thinkphp框架的异常处理；web的api部分用的thinkphp。支持特性如下：

1.支持万人同时在线聊天。

2.支持消息类型有文本、图片、语音消息、短视频、富文本、文件。

3.支持windows、linux系统。

4.支持composer安装（推荐）。

5.支持聊天类型有好友聊天、群聊、客服、计时咨询。

[demo示例](http://chat.blogts.com/client/chat/html5/)

[online_chat官网地址](http://chat.blogts.com/)

[客户端下载地址](https://gitee.com/ttlt/online_chat_client)

#### 通过composer获取服务端代码
服务端composer安装命令：
```
composer require dzgz/online-chat
```
在入口文件里面引入public/index.php，代码如下：
```php
include __DIR__ . '/../vendor/dzgz/online-chat/public/index.php';
```
导入sql文件：vendor/dzgz/online-chat/online_chat.sql

#### 服务端结构
online_chat服务端是由server服务程序和web的api组成；软件结构图如下：
![输入图片说明](https://chat.blogts.com/static/img/software-jiegou.jpg)

#### 客户端截图
![输入图片说明](https://chat.blogts.com/static/img/lb.123.jpg)
![输入图片说明](https://chat.blogts.com/static/img/lb1.jpg)

#### 安装和配置

1.安装PHP扩展：socket、event、fileinfo、openssl。

2.导入数据库文件：online_chat.sql。

3.修改数据库配置文件config/database.php或者vendor/dzgz/online-chat/config/database.php。

4.修改config/chat.php或者vendor/dzgz/online-chat/config/chat.php。

![输入图片说明](https://chat.blogts.com/static/img/chat-config.jpg)

#### 服务程序启动

调试方式启动
```shell
php server.php 
```
守护进程方式启动
```
php server.php daemon
```

#### socket api
##### 消息示例
``` 
  {
	"chat_type": 0,
	"to_id": 10000,
	"access_token": "45b3a097a48d0a15cd2e0f3361070268",
	"msg_type": 0,
	"msg": "这是一个消息"
}
```
##### 字段说明：

|参数名|必选|类型|说明|
|:----    |:---|:----- |-----   |
|chat_type |是  |int |聊天类型   |
|to_id |是  |int | 和谁聊天的id    |
|access_token     |是  |string | 访问的token    |
|msg_type     |是  |int | 消息类型    |
|msg     |是  |string | 消息内容    |

[查看更多 socket api](http://chat.blogts.com/#/doc/showdoc/web/?#/4?page_id=22)

#### web api
```
POST /index.php/online_chat/chat/doLogin
```

##### 参数：

|参数名|必选|类型|说明|
|:----    |:---|:----- |-----   |
|app_uid |是  |string |接入的应用的uid   |
|name |是  |string | 昵称    |
|head_img     |是  |string | 头像    |
|user_type     |是  |string | 用户类型    |
|time     |否  |string | 当前时间戳；跟当前时间相差不能超过30秒；开启签名验证需要该参数    |
|sign     |否  |string | 签名；开启签名验证需要该参数    |

 ##### 返回示例

``` 
  {
	"code": 200,
	"msg": "登录成功！",
	"data": {
		"is_mobile": 0
	}
 }
```

##### 返回参数字段说明

|参数名|类型|说明|
|:-----  |:-----|-----                           |
|code |int   |状态码，200代表成功，其他失败  |
|msg |int   |消息说明  |
|data |object   |数据  |
|data.is_mobile |int   |0-不是移动端，1-是移动端  |

##### 签名算法：
代码例子如下：
```php
<?php
$params = [
	'app_uid'=>$_POST['app_uid'], //所在应用的uid
	'name'=>$_POST['name'], //昵称
	'head_img'=>$_POST['head_img'], //头像
	'time'=>$_POST['time'], //时间，跟当前时间相差不能超过30秒
	'user_type'=>$user_type, //用户类型
	'sign_key'=>config('chat.sign_key') //签名key
];
ksort($params);
$str = http_build_query($params);
if( md5($str) != $_POST['sign'] ){
	returnMsg(100,'签名不正确！');
}
```
[查看更多 web api](http://chat.blogts.com/#/doc/showdoc/web/?#/4?page_id=22)

#### 常见问题
##### 1.event扩展在哪里下载？

[http://pecl.php.net/package/event]

##### 2.安装event扩展失败？

安装event扩展需要先安装socket扩展。

##### 3.为什么服务端和客服端是两个版本库？

为了方便安装online_chat，采用composer安装方式;为了方便在现有系统里面集成online_chat;服务端和客服端是通过api对接起来的；在集成online_chat可以选择部分集成；所以分成服务端和客服端两个版本库。

##### 4.支持windows系统？

支持windows系统。

##### 5.为什么不使用swoole扩展、workerman、ReactPHP?

不使用swoole扩展是因为不支持windows；其实用workerman或者ReactPHP的话，可以节省很多时间，还可以提高服务程序稳定性；之所以直接用event扩展，以及写websocket协议解析，一是因为我对workerman框架、ReactPHP框架没有在项目中使用过，再者想对服务程序这一块进一步提升。

##### 6.online_chat服务程序底层可以扩展成使用swoole扩展、workerman、ReactPHP吗？

可以；server类和connection类是继承了基类的，使用swoole扩展、workerman、ReactPHP来写server类和connection类。

##### 7.web api有sdk吗？

有js版本的sdk。

##### 8.可以查看当前运行信息？

可以的。

命令：php subscriber.php 主题;条件=1;条件=1 主题;条件=1;条件=1

php subscriber.php all

hp subscriber.php all;uid=1

php subscriber.php message serverInfo

##### 9.online_chat的服务程序支持ssl吗？

没有ssl的；设计的是前面会有一个nginx服务器或者apache服务器，由nginx服务器或者apache服务器来提供ssl。

##### 10.为什么没有管理端？

采用composer安装方式；online_chat设计成给现有的项目引入的。
