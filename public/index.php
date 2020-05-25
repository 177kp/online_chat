<?php
namespace think;

$uri = $_SERVER['REQUEST_URI'];
if( !preg_match('/^(\/index\.php\/online_chat\/.*|\/online_chat\/.*)/',$uri) ){
    return;
}

// [ 应用入口文件 ]

// 支持事先使用静态方法设置Request对象和Config对象

if( is_dir(__DIR__ . '/../../../dzgz') ){
    define('APP_ROOT_DIR',realpath(__DIR__ . '/../../../../'));
}else{
    define('APP_ROOT_DIR',realpath(__DIR__ . '/../') );
}
include APP_ROOT_DIR . '/vendor/topthink/framework/base.php';

//echo APP_ROOT_DIR;exit;
if( !empty(APP_ROOT_DIR) ){
    define('RUNTIME_DIR',APP_ROOT_DIR . '/runtime');
}else{
    define('RUNTIME_DIR','');
}
if( !is_dir(RUNTIME_DIR) ){
    mkdir(RUNTIME_DIR,0755);
}

// 执行应用并响应
$App = new \think\App(__DIR__ . '/../application/');
$App->initialize();

allowCrossDomain();

//online chat session作用域
\think\facade\Hook::add('app_init',function(){
    //判断HTTP_TOKEN是否合法
    if( !empty($_SERVER['HTTP_TOKEN']) && !preg_match('/^[\w]{20,40}$/',$_SERVER['HTTP_TOKEN']) ){
        returnMsg(100,'HTTP_TOKEN不合法！');
    }
    //http的头字段有token，并且不为空，并且uri不是登陆地址，用token作为session_id;
    if( !empty($_SERVER['HTTP_TOKEN']) && strtolower($_SERVER['HTTP_TOKEN']) != 'null' && !preg_match( '/\/online_chat\/chat\/doLogin$/',\think\facade\Request::url() ) ){
        //使用token作为session_id,为了跨域；前端sdk里面传的token。
        \think\facade\Session::init([
            'prefix'         => 'online_chat',
            'type'           => '',
            'auto_start'     => true,
            'id' => $_SERVER['HTTP_TOKEN']
        ]);
    }else{
        \think\facade\Session::init([
            'prefix'         => 'online_chat',
            'type'           => '',
            'auto_start'     => true
        ]);
    }
    
});
session_write_close();
$App->run()->send();