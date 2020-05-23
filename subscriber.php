<?php
/**
 * 订阅者信息
 * 命令：php subscriber.php 主题;条件=1;条件=1 主题;条件=1;条件=1
 * php subscriber.php all
 * php subscriber.php all;uid=1
 * php subscriber.php message serverInfo
 */
class Subscriber{
    /**
     * @var EventBase $EventBase
     */
    private $EventBase;
    /**
     * @var array $TimerEvents 定时器事件
     */
    private $TimerEvents;
    /**
     * @var array 事件
     */
    private $Events;
    /**
     * @var array $topics 主题
     */
    private $topics;
    /**
     * @var string $serverHost server的主机地址
     */
    private $serverHost = '127.0.0.1';
    /**
     * @var int $serverHttpPort server的http端口号
     */
    private $serverHttpPort = 3080;
    /**
     *@var string $subscriberHost 订阅者主机地址 
     */
    private $subscriberHost = '127.0.0.1';
    /**
     * @var string $subscriberPort 订阅者端口号
     */
    private $subscriberPort = 10002;
    /**
     * @var resource $subscriberSocket 订阅者的socket
     */
    private $subscriberSocket;
    /**
     * 实例化
     * @param array $configs
     * [
     *    serverHost=>server的主机地址,
     *    serverHttpPort=>server的主机端口号
     * ]
     */
    public function __construct($configs){
        global $argv;
        $this->EventBase = new EventBase();
        $topics = [];
        foreach( $argv as $k=>$param ){
            if( $k== 0 ){
                continue;
            }
            $arr = explode(';',$param);
            $topic['name'] = $arr[0];
            $topic['conditions'] = []; 
            foreach( $arr as $j=>$a ){
                if( $j == 0 ){
                    continue;
                }
                $condition =explode('=',$a);
                //var_export($condition);exit;
                if( count($condition) == 2 ){
                    $topic['conditions'][$condition[0]] = $condition[1];
                }
            }
            $topics[] = $topic;
        }
        //var_dump($topics);
        $this->topics = $topics;
        if( isset($configs['serverHost']) ){
            $this->serverHost = $configs['serverHost'];
        }
        if( isset($configs['serverHttpPort']) ){
            $this->serverHost = $configs['serverHttpPort'];
        }
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
     * 添加订阅者
     */
    public function addSubscriber(){
        $subscriberUrl = 'http://'.$this->serverHost.':'.$this->serverHttpPort.'?' . http_build_query([
            'action'=>'publisher/addSubscriber',
            'subscriber'=>[
                'topics'=>$this->topics,
                'addr'=>[
                    'host'=>$this->subscriberHost,
                    'port'=>$this->subscriberPort
                ]
            ]
        ]);
        $res = file_get_contents($subscriberUrl);
        //echo $res . PHP_EOL;
    }
    /**
     * 接收消息
     */
    private function recvMsg(){
        socket_recvfrom($this->subscriberSocket,$buffer,65535,0,$ip,$port);
        if( empty($buffer) ){
            return;
        }
        echo $buffer . PHP_EOL;
    }
    /**
     * 启动订阅者
     */
    public function start(){
        $this->subscriberSocket = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
        socket_bind($this->subscriberSocket,$this->subscriberHost,$this->subscriberPort);
        socket_set_nonblock($this->subscriberSocket);

        $this->Event = new Event($this->EventBase,$this->subscriberSocket,Event::READ | Event::PERSIST,function(){
            try{
                $this->recvMsg();
            }catch( \Exception $e ){
                echo (string)$e;
            }
        });
        $res = $this->Event->add();
        $this->addSubscriber();
        //每隔30秒订阅一次，每次订阅的有效期是60秒
        $this->timer(function(){
            $this->addSubscriber();
        },30);
        $this->EventBase->loop();
    }
}

ob_implicit_flush(true); //打开缓冲区刷送
$Subscriber = new Subscriber([
    'serverHost'=>'127.0.0.1',
    'serverPort'=>3080
]);
$Subscriber->start();