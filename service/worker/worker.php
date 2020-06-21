<?php
use think\Db;
use think\db\Connection;
use onlineChat\lib\ExceptionHandle;
use onlineChat\lib\Daemon;
use onlineChat\model\Database;
include __DIR__ . '/../init.php';

//设置worker进程用户
Daemon::setOsUser('www');
$timeCount = 0; //时间计数，单位秒
while(1){
    $msg = fgets(STDIN,65535);
    $msg = trim($msg);
    //safe_dump( 'woker: recv msg ' . $msg );
    if( preg_match('/^timer/',$msg) ){ //每隔一秒收到的消息
        //safe_dump("worker:recv " . $msg);
        try{
            $dbConn = Database::instance()->getDbConn();
            if( $dbConn->inTransaction() ){
                $dbConn->commit();
                //safe_dump('worker:提交数据库事务！');
            }
            if( $timeCount>1800 ){
                $timeCount = 0;
                $dbConn = Database::instance()->getDbConn(); //重连数据库
                safe_dump("worker:重连数据库");
                //safe_dump( $dbConn );
            }
            //safe_dump($timeCount);
            $timeCount++;
        }catch(\Exception $e){
            ExceptionHandle::chatRenderException($e);
        }
        continue;
    }elseif( $msg == "" ){ //server退出的消息
        $dbConn = Database::instance()->getDbConn();
        if( $dbConn->inTransaction() ){
            $dbConn->commit();
            //safe_dump('worker:提交数据库事务！');
        }
        exit;
    }
    try{
        //保存消息
        saveMessage($msg);
    }catch(\Exception $e){
        ExceptionHandle::chatRenderException($e);
    }
}

function saveMessage($msg){
    //safe_dump($msg);
    $data = json_decode($msg,true);
    if( empty($data) ){
        return;
    }
    //safe_dump($data);
    if( !isset($data['topic']) ){
        return;
    }
    if( !isset($data['msg']) ){
        return;
    }
    $dbConn = Database::instance()->getDbConn();
    if( $dbConn == null ){
        //safe_dump('worker:数据库连接为NULL!');
    }
    if( !$dbConn->inTransaction() ){
        $dbConn->beginTransaction();
        //safe_dump('worker:开启数据库事务！');
    }
    $params = $data['msg'];
    if( $data['topic'] == 'message' ){
        Database::instance()->insertMessage($params);
    }elseif( $data['topic'] == 'offline' ){
        Database::instance()->setUserOffline($params['uid'],$params['tmp']);
    }elseif( $data['topic'] == 'heartBeat' ){
        Database::instance()->updateHeartbeat($params['users']);
    }elseif( $data['topic'] == 'consult_time' ){
        Database::instance()->update_consult_time($params['consult_time']);
    }elseif( $data['topic'] == 'online' ){
        Database::instance()->updateHeartbeat([
            [
                'uid'=>$params['uid'],
                'tmp'=>$params['tmp']
            ]
        ]);
    }
}
