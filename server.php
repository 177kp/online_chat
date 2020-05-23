<?php
ini_set('display_errors','on');
include __DIR__ . '/service/lib/Daemon.php';
if( is_dir(__DIR__ . '/../../dzgz') ){
    define('APP_ROOT_DIR',realpath(__DIR__ . '/../../../'));
}else{
    define('APP_ROOT_DIR',__DIR__ );
}
$Daemon = new onlineChat\lib\Daemon([
    'scriptFile'=>__DIR__ . '/service/server/server.php',
    'pidFile'=>APP_ROOT_DIR. '/runtime/server.pid'
]);
$Daemon->start();