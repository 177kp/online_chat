<?php

error_reporting( E_ALL );
ini_set('display_errors','ON');
include __DIR__ . '/../../service/lib/protocol/Websocket.php';
include_once __DIR__ . '/../../service/lib/websocketServer/ConnectionBase.php';
include_once __DIR__ . '/../../service/lib/websocketServer/event/Connection.php';
/**
 * 测试用的websocket客户端
 * 命令行 php client.php 多少个客户端 发送多少轮 每轮用多少个客户端发送消息 消息最大长度
 */
class Client{
    /**
     * @var EventBase $EventBase
     */
    private $EventBase;
    /**
     * @var array $TimerEvents 定时器事件
     */
    private $TimerEvents;
    /**
     * @var Websocket $Websocket
     */
    private $Websocket;
    /**
     * @var int $clientCount 客户端数量
     */
    private $clientCount = 100;
    /**
     * @var int $sendRound 发送多少轮
     */
    private $sendRound = 100;
    /**
     * @var int $sendCount 发送多少次
     */
    private $sendCount = 100;
    /**
     * @var int $msgMaxLength 消息最大长度
     */
    private $msgMaxLength = 10;
    /**
     * @var string $serverHost server的主机地址
     */
    private $serverHost = '127.0.0.1';
    /**
     * @var int $serverWebsocketPort server的websocket端口号
     */
    private $serverWebsocketPort = 2080;
    /**
     * @var int $serverHttpPort server的http端口号
     */
    private $serverHttpPort = 3080;
    /**
     * @var int $recvMsgCount 接收到消息数量
     */
    private $recvMsgCount = 0;
    /**
     * @var array $clients 客户端
     */
    private $clients = [];
    /**
     * @var int $totalSendRound 总计发送多少轮
     */
    private $totalSendRound = 0;
    /**
     * @var int $totalSendCount 总计发送次数
     */
    private $totalSendCount = 0;

    /**
     * 实例化
     */
    public function __construct(){
        global $argv;
        $this->Websocket = new onlineChat\lib\protocol\Websocket();
        //客户端数量
        if( isset($argv[1]) ){
            $this->clientCount = (int)$argv[1];
        }
        if( $this->clientCount > 9999 ){
            throw new \Exception('客户端不能大于9999！');
        }
        //发送几轮
        if( isset($argv[2]) ){
            $this->sendCount = (int)$argv[2];
        }
        //每轮发送多少条消息
        if( isset($argv[3]) ){
            $this->sendPerCount = (int)$argv[3];
        }
        //消息的msg的最大长度
        if( isset($argv[4]) ){
            $this->msgMaxLength = (int)$argv[4];
        }
        if( $this->msgMaxLength > 65000 ){
            throw new \Exception('msgMaxLength不能大于65000!');
        }
        $this->EventBase = new EventBase();
        $this->insertUsers();
    }
    public function insertUsers(){
        $dbConfig = include __DIR__ . '/../../config/database.php';
        try{
            $pdo = new \PDO($dbConfig['type'] . ':dbname=' . $dbConfig['database'] . ';host=' . $dbConfig['hostname'], $dbConfig['username'], $dbConfig['password'],[
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE =>\PDO::FETCH_ASSOC
            ]);
        }catch( \Exception $e ){
            echo '数据库连接失败！' . PHP_EOL;
            return;
        }
        $test_users = $pdo->query('select uid from chat_user where uid < 10000')->fetchAll();
        $test_uids = array_column($test_users,'uid');
        $pdo->beginTransaction();
        $sql = 'insert into chat_user(uid,name,head_img,online,last_login_time,app_uid,user_type,last_heartbeat_time,soft_delete)
                    values(?,?,?,0,0,?,0,0,0)';
        $sth = $pdo->prepare($sql);
        //var_dump($test_uids);exit;
        for( $test_uid=1;$test_uid<=$this->clientCount;$test_uid++ ){
            //echo $test_uid . PHP_EOL;
            if( !in_array($test_uid,$test_uids) ){
                $name = 'test_name_' . $test_uid;
                $head_img = '/static/img/head_img/' . ($test_uid % 17) . '.jpg';
                $app_uid = 'test_app_uid_' . $test_uid;
                $sth->execute([$test_uid,$name,$head_img,$app_uid]);
            }
        }
        $pdo->commit();
    }
    /**
     * 实现定时器
     * @param callable $callback 可回调
     * @param int $interval 时间间隔，单位秒
     * @return string 定时器的key
     */
    public function timer(callable $callback,$interval){
        if( empty($this->EventBase) ){
            $this->EventBase = new \EventBase();
        }
        $key = uniqid();
        $this->TimerEvents[$key] = \Event::timer($this->EventBase, function()use($callback,$interval,$key){
            $callback();
            $this->TimerEvents[$key]->addTimer($interval);
        });
        $this->TimerEvents[$key]->addTimer($interval);
        return $key;
    }
    /**
     * 获取access_token
     * @param int $uid 用户id
     */
    public function getAccessToken($uid){
        global $host;
        global $httpPort;
        $name = 'name' . $uid;
        $head_img = '/static/img/head_img/' . ($uid % 17) . '.jpg';
        $params = [
            'action'=>'user/get_access_token',
            'uid'=>$uid,
            'name'=>$name,
            'head_img'=>$head_img,
            'user_type'=>0,
            'session_uids'=>[]
        ];
        $url = 'http://' . $this->serverHost . ':' . $this->serverHttpPort . '?' . http_build_query($params);
        $time1 = microtime(true);
        $res = $this->http_get($url);
        //echo microtime(true) - $time1 . PHP_EOL;
        $res = json_decode($res,true);
        //var_dump($res);exit;
        $access_token = $res['data']['access_token'];
        
        return $access_token;
    }
    private function http_get($url){

        static $socket;
        $param = parse_url($url);
        if( !isset($param['path']) ){
            $param['path'] = '/';
        }
        if( $socket == null ){
            $socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
            socket_connect($socket,$param['host'],$param['port']);
        }
        
        $http_request = "GET ".$param['path']."?" .  $param['query'] .  " HTTP/1.1\r\n".
        "Host:" . $param['host'] . "\r\n".
        "Connection: keep-alive\r\n\r\n";
        socket_write($socket,$http_request);
        $buffer = '';
        while( 1 ){
            $res = socket_read($socket,1024,PHP_NORMAL_READ );
            if( $res === false ){
                break;
            }
            $buffer .= $res;
            if( substr($buffer,-4) == "\r\n\r\n" ){
                break;
            }
        }
        $header = $buffer;
        $arr = explode("\r\n",$header);
        unset($arr[0]);
        $heads = [];
        foreach( $arr as $head  ){
            $item = explode(':',$head);
            if( count($item) == 2 ){
                $heads[ strtolower($item[0]) ] = $item[1];
            }
        }
        
        if( !isset($heads['content-length']) ){
            if( $socket != null ){
                socket_close($socket);
            }
            $this->http_socket = null;
            return;
        }
        $bodyLength = $heads['content-length'];
        $body = socket_read($socket,$bodyLength);
        return $body;
    }
    /**
     * 接收消息
     * @param EventBufferEvent $bev
     */
    private function recvMsg($bev){
        foreach( $this->clients as $key=>$client ){
            if(  $client['bev']->fd == $bev->fd ){
                $uid = $key;
                break;
            }
        }
        if(  !isset($this->clients[$uid]['Connection']) ){
            $this->clients[$uid]['Connection'] = new onlineChat\lib\websocketServer\event\Connection($bev);
        } 
        do{
            try{
                $frame = $this->Websocket->readFrame( $this->clients[$uid]['Connection'] );
                //var_dump($frame);
                if( $frame == false ){
                    return;
                }
            }catch( \Exception $e ){
                echo $e;
                return;
            }
            if( $frame == false ){
                return;
            }
            $info = [
                'uid:' . $uid,
                'recv msg length:'  . strlen($frame['payload-data']),
                //'payload-data:' . $frame['payload-data'],
            ];
            //var_export($frame);
            echo implode('; ',$info) . PHP_EOL;
            $this->recvMsgCount++;
        }while(1);
    }
    /**
     * 发送消息
     * @param int $uid 用户id
     */
    private function sendMsg($uid){
        if( !isset($this->clients[$uid]) ){
            return;
        }
        $client = $this->clients[$uid];
        $msg =  str_repeat(".",mt_rand(1,$this->msgMaxLength));
        //$msg =  str_repeat(".",mt_rand(1,3000));
        if( $this->clientCount == 1 ){
            $to_id = 2;
        }else{
            do{
                $to_id = mt_rand(1,$this->clientCount);
                if( $to_id != $uid ){
                    break;
                }
            }while(1);
        }
        $head_img = '/static/img/head_img/' . ($uid % 17) . '.jpg';
        $sendMsg = [
            'uid'=>$uid,
            'name'=>'name' . $uid,
            'head_img'=>$head_img,
            'chat_type'=>0,
            'to_id'=>$to_id,
            'msg'=>$msg,
            'msg_type'=>0,
            'access_token'=>$client['access_token']
        ];
        $sendMsg = json_encode($sendMsg);
        $sendMsg1 = $sendMsg;

        $sendMsg = $this->Websocket->genFrame([
            'payload-data'=>$sendMsg,
            'masked'=>1
        ]);
        $res =  socket_send($client['socket'],$sendMsg,strlen($sendMsg),0);
        if( $res == false ){
            var_dump($res);exit;
        }
        //usleep(10000);
        //echo str_repeat('#',20) . ' ' . $sendMsg1 . PHP_EOL;
        $this->totalSendCount++;
    }
    /**
     * 启动client
     */
    public function start(){
        $time1 = microtime(true);
        //创建客服端，创建socket，事件
        for($i=1;$i<=$this->clientCount;$i++){
            
            $uid = $i;
            $access_token = $this->getAccessToken($uid);
            
            $socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
            socket_connect($socket,$this->serverHost,$this->serverWebsocketPort);
            $randKey = base64_encode( uniqid() );
            $str = $this->Websocket->genHandShakeRequest($this->serverHost,$this->serverWebsocketPort,'/?access_token='.$access_token,$randKey);
            socket_send($socket,$str,strlen($str),0);
            //usleep(100000);
            socket_set_nonblock($socket);
            $bev = new EventBufferEvent($this->EventBase,$socket,EventBufferEvent::OPT_CLOSE_ON_FREE | EventBufferEvent::OPT_DEFER_CALLBACKS,function($bev) use($randKey) {
                if( $bev->input->substr(0,8) == 'HTTP/1.1' ){
                    $str = $bev->input->read(1024);
                    $this->Websocket->verifyUpgrade($str,$randKey);
                }else{
                    try{
                        $this->recvMsg($bev);
                    }catch( \Exception $e ){
                        echo (string)$e;
                    }
                }
            });
            
            $bev->enable(Event::READ);
            $this->clients[$uid] = [
                'socket'=>$socket,
                'bev'=>$bev,
                'access_token'=>$access_token,
                'name'=>'name'.$uid
            ];
            if( $i % 1000 == 0 ){
                echo str_repeat('-',20) . "创建了".$i."客户端 ！用户了" . round( (microtime(true) - $time1),2) .  PHP_EOL;
            }
        }

        if( $i % 1000 != 0 ){
            echo str_repeat('-',20) . "创建了".$this->clientCount."客户端 ！" . round( (microtime(true) - $time1),2) .  PHP_EOL;
        }

        $this->timer(function(){

            for( $i=1;$i<=$this->sendPerCount;$i++){
                $this->sendMsg($i);
            }
            $this->totalSendRound++;
        },5);
        $this->timer(function(){
            if( $this->totalSendCount == $this->sendCount ){
                $complete = "发送完毕！";
            }else{
                $complete = "";
            }
            echo str_repeat('*',20) . ' ' . $complete.  "发送".$this->totalSendRound."轮；发送".$this->totalSendCount."条消息；收到消息总计：" . $this->recvMsgCount . PHP_EOL;
            //unset($this->clients[mt_rand( 0 ,$this->clientCount )]);
        },2);
        $this->EventBase->loop();
    }

}

ob_implicit_flush(true); //打开缓冲区刷送
$Client = new Client();
$Client->start();