<?php
ini_set('log_errors','off');

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

$App = new \think\App(dirname(__DIR__) . '/application/');
$App->initialize();

if( config('app_debug') ){
    ob_implicit_flush(true); //打开缓冲区刷送
}
if( !function_exists('safe_dump') ){
    /**
     * 安全打印，用于命令行程序，有终端的情况才打印
     */
    function safe_dump($var){
        if( !function_exists('posix_isatty') || posix_isatty(STDOUT) ) {
            if( is_string($var) ||is_numeric($var) ){
                echo $var .PHP_EOL;
            }elseif(  is_array($var) ){
                var_export($var);
            }else{
                echo (string)$var . PHP_EOL;
            }
        }
    }
}

if( !preg_match("/cli/i", php_sapi_name()) ){
    echo '请在命令行启动' . PHP_EOL;
    exit;
}
if( preg_match("/server\/server\.php$/" ,$argv[0]) ){
    cli_set_process_title("php online chat server");
}elseif( preg_match("/worker\/worker\.php$/" ,$argv[0]) ){
    cli_set_process_title("php online chat worker");
}