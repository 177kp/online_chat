<?php
use think\Db;
use onlineChat\model\Session;
use onlineChat\model\Room;
use onlineChat\model\Database;
use onlineChat\model\Message;
use onlineChat\model\Publisher;
use onlineChat\model\ConsultTime;
use onlineChat\lib\ExceptionHandle;
use onlineChat\server\WebsocketCallback;
use onlineChat\server\HttpApi;
use think\facade\Log;
use onlineChat\lib\protocol\Websocket;
use onlineChat\lib\Daemon;

include dirname(__DIR__) . '/init.php';

//设置内存限制大小
ini_set('memory_limit', config('chat.server.memory_limit'));

if( Database::instance()->getDbConn() == null ){
    exit;
}
//初始化聊天室数据
Room::initData();
//关闭数据库连接
Database::instance()->closeDbConn();
/**
 * @var \onlineChat\lib\websocketServer\Base server类
 */
$WebSocketServer = new \onlineChat\lib\websocketServer\event\Server([
    'websocket'=>[
        'host'=>config('chat.server.host'),
        'port'=>config('chat.server.websocket_port')
    ],
    'http'=>[
        'host'=>config('chat.server.host'),
        'port'=>config('chat.server.http_port')
    ]
]);
//websocket握手回调
$WebSocketServer->on('websocketHandshake',function($Connection,$request,$Exception){
    try{
        if( $Exception != null ){
            throw $Exception;
        }
        //throw new \Exception('test');
        \onlineChat\server\websocket\Handshake::callback($Connection,$request);
    }catch( \Exception $e ){
        ExceptionHandle::chatRenderException($e);
        Session::del($Connection->fd);
    }
});

//消息处理回调
$WebSocketServer->on('websocketMessage',function($Connection,$frame,$Exception){
    //safe_dump($frame);
    try{
        if( $Exception != null ){
            throw $Exception;
        }
        \onlineChat\server\websocket\OnMessage::callback($Connection,$frame);
    }catch( \Exception $e ){
        //echo $e;
        ExceptionHandle::chatRenderException($e);
        //safe_dump( $Connection->fd );
        Session::del($Connection->fd);
    }
});

//链接关闭，清除数据
$WebSocketServer->on('websocketClose',function($fd,$Exception){
    try{
        if( $Exception != null ){
            throw $Exception;
        }
        //throw new \Exception('test');
        \onlineChat\server\websocket\Close::callback($fd);
    }catch( \Exception $e ){
        ExceptionHandle::chatRenderException($e);
        //safe_dump( $Connection->fd );
        Session::del($fd);
    }
    /*
    global $WebSocketServer;
    if( !isset(Session::$fdIndex[$fd]) ){
        return;
    }
    $access_token = Session::$fdIndex[$fd];
    $session = Session::$sessions[$access_token];
    $sessions = Session::getByUid($session['uid'],$session['tmp']);
    if( count($sessions) == 1 ){
        $msg = [
            'code'=>200,
            'topic'=>'offline',
            'msg'=>[
                'uid'=>Session::$sessions[$access_token]['uid'],
                'tmp'=>$session['tmp'],
                'ctime'=>time()
            ]
        ];
        $msg = json_encode($msg);

        //safe_dump( Session::$sessions );
        //safe_dump( Session::$sessions[$access_token]['session_uids'] );
        foreach( Session::$sessions[$access_token]['sessions'] as $tmpSession ){
            $sessions = Session::getByUid($tmpSession['to_id'],$tmpSession['tmp']);
            foreach( $sessions as $session ){
                //safe_dump($msg);
                Websocket::instance()->writeFrame($session['connection'],$msg);
            }
        }
    }
    Session::del($fd);
    //safe_dump( count(Session::$sessions) );
    */
});

function httpApiMsg($code,$msg,$data = []){
    return json_encode([
        'code'=>$code,
        'msg'=>$msg,
        'data'=>$data
    ],JSON_UNESCAPED_UNICODE);
}
//http消息
$WebSocketServer->on('httpMessage',function($request){
    try{
        //var_dump( $request['query'] );
        parse_str($request['query'],$get);
        //safe_dump($get);
        $action = explode('/',$get['action']);
        if( count($action) != 2 ){
            return httpApiMsg(100,'action不正确！');
        }
        $classes = ['chatRoom','consultTime','customer','publisher','user'];
        if( !in_array($action[0],$classes) ){
            return httpApiMsg(100,'action不正确！');
        }
        $action[0] = ucfirst($action[0]) . 'Api';
        $class = '\onlineChat\server\http\\'.$action[0];
        if( !is_callable($class . '::' . $action[1]) ){
            return httpApiMsg(100,'action不正确！');
        }
        return call_user_func_array($class . '::' . $action[1],[$get]);
    }catch( \Exception $e ){
        ExceptionHandle::chatRenderException($e);
    }
});

$start_time = time();

//打印内存信息
$WebSocketServer->timer(function(){
    global $start_time;
    try{
        if( config('chat.server.dump_memory') ){
            $arr = [
                '内存'=> round( memory_get_usage() / 1024 , 2) . 'KB',
                '连接'=>count(Session::$sessions),
                '启动时间'=>date("Y-m-d H:i",$start_time),
                '运行时间'=> round( ( time() - $start_time ) / 3600 , 2) . '小时',
                'websocket缓存包'=>Websocket::instance()->getFrameCount()
            ];
            safe_dump( json_encode($arr,JSON_UNESCAPED_UNICODE) );
        }
        $msg = Message::genServerInfo([
            '内存'=> round( memory_get_usage() / 1024 , 2) . 'KB',
            '连接'=>count(Session::$sessions),
            '启动时间'=>date("Y-m-d H:i",$start_time),
            '运行时间'=> round( ( time() - $start_time ) / 3600 , 2) . '小时'
        ]);
        Publisher::instance()->publish($msg);
    }catch( \Exception $e ){
        ExceptionHandle::chatRenderException($e);
    }
},5);

//计时咨询
$WebSocketServer->timer(function(){
    foreach( ConsultTime::$consult_times as $id=>$consult_time ){
        if( $consult_time['status'] != '1' ){
            ConsultTime::del($id);
            continue;
        }
        //用户和咨询师都不在线的情况，就暂停
        if( !Session::exist($consult_time['uid']) || !Session::exist($consult_time['to_id']) ){
            ConsultTime::$consult_times[$id]['status'] = 2;
            $msg = Message::genConsultTimeMessage($id);
            Publisher::instance()->publish($msg);
            Session::writeFrameByUid($consult_time['uid'],Session::USER_NORMAL,$msg);
            Session::writeFrameByUid($consult_time['to_id'],Session::USER_NORMAL,$msg);
            ConsultTime::del($id);
            continue;
        }
        //计时
        if( $consult_time['free_duration_count'] == 0 ){
            if( $consult_time['duration_count'] > 10 ){
                ConsultTime::$consult_times[$id]['duration_count'] -= 10;
            }else{
                ConsultTime::$consult_times[$id]['duration_count'] = 0;
                ConsultTime::$consult_times[$id]['status'] = 3;
            }
        }elseif( $consult_time['free_duration_count'] > 10 ){
            ConsultTime::$consult_times[$id]['free_duration_count'] -= 10;
        }else{
            ConsultTime::$consult_times[$id]['free_duration_count'] = 0;
        }
        $msg = Message::genConsultTimeMessage($id);
        Publisher::instance()->publish($msg);
        Session::writeFrameByUid($consult_time['uid'],Session::USER_NORMAL,$msg);
        Session::writeFrameByUid($consult_time['to_id'],Session::USER_NORMAL,$msg);
    }
},10);

//心跳
$WebSocketServer->timer(function(){
    $count = count(Session::$sessions);
    $uids = [];
    foreach( Session::$sessions as $session ){
        $uids[] = $session['uid'];
        if( count($uids) >= 50 ){
            $msg = Message::genHeartBeatMessage($uids);
            Publisher::instance()->publish($msg);
            $uids = [];
        }
    }
    if( count($uids) > 0 ){
        $msg = Message::genHeartBeatMessage($uids);
        Publisher::instance()->publish($msg);
    }
},30);

$descriptorspec = array(
    0 => array("pipe", "r"),  // 标准输入，子进程从此管道中读取数据
    //1 => array("pipe", "w"),  // 标准输出，子进程向此管道中写入数据
    //2 => array("file", "error-output.txt", "a")
 );
 $workerProcess = [];
//启动worker进程
$workerProcess['process'] = proc_open('php ' . realpath(__DIR__ . '/../worker') . '/worker.php', $descriptorspec, $pipes,NULL, NULL);
$workerProcess['pipes'] = $pipes;
//safe_dump($pipes);
$WebSocketServer->timer(function(){
    global $workerProcess;
    global $descriptorspec;
    $status = proc_get_status($workerProcess['process']);
    if( $status['running'] == false ){
        foreach( $workerProcess['pipes'] as $pipe ){
            is_resource($pipe) && fclose($pipe);
        }
        proc_close($workerProcess['process']);
        $workerProcess['process'] = proc_open('php ' . realpath(__DIR__ . '/../worker') . '/worker.php', $descriptorspec, $pipes,NULL, NULL);
        $workerProcess['pipes'] = $pipes;
        echo "server: worker挂掉；重启worker" . PHP_EOL;
    }
    fwrite( $workerProcess['pipes'][0],'timer 1 second ' . "\n");
},1);
//清除未连接的session
$WebSocketServer->timer(function(){
    $time = time();
    foreach( Session::$tmpSessions as $access_token=>$session ){
        if( $session['connection'] == null && $session['time'] < time() - 30 ){
            //echo '清除连接' . PHP_EOL;
            unset(Session::$tmpSessions[$access_token]);
        }
    }
},30);


//设置server进程用户
Daemon::setOsUser('www');

//开始事件循环
$WebSocketServer->loop();