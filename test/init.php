<?php
ini_set('log_errors','off');
include __DIR__ . '/../../../topthink/framework/base.php';

define('APP_ROOT_DIR',realpath(__DIR__ . '/../../../../'));
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

include_once __DIR__ . '/common/Connection.php';

if( !function_exists('httpApiMsg') ){
    function httpApiMsg($code,$msg,$data = []){
        return json_encode([
            'code'=>$code,
            'msg'=>$msg,
            'data'=>$data
        ],JSON_UNESCAPED_UNICODE);
    }
}