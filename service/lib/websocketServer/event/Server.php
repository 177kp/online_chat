<?php
namespace onlineChat\lib\websocketServer\event;

use EventListener;
use EventBufferEvent;
use EventBase;
use EventUtil;
use onlineChat\lib\websocketServer\Base;
use onlineChat\lib\websocketServer\ConnectionBase;
use onlineChat\lib\protocol\Websocket;
class Server extends Base{
    /**
     * @var $EventBase 事件基础类实例
     */
    protected $EventBase;
    /**
     * @var $listener EventListener的实例
     */
    private $listener;
    /**
     * @var $Connections 连接的数组；item instanceof ConnectionBase
     */
    protected $Connections = [];
    /**
     * @var $EventHttp EventHttp的实例
     */
    private $EventHttp;
    /**
     * @var $TimerEvents array 定时器实例数组
     */
    private $TimerEvents = [];
    /**
     * 数据包buffer
     */
    private $packetBuffer = [];
    /**
     * 进程信号事件数组
     */
    private $signals = [];
    /**
     * 当前对象销毁时，清空连接
     */
    public function __destruct() {
        foreach ($this->Connections as &$c) $c = NULL;
    }
    /**
     *实现websocket的server
     */
    public function websocketServer(){

        if( empty($this->EventBase) ){
            $this->EventBase = new EventBase();
        }
        /**
         * https://www.php.net/manual/zh/eventlistener.construct.php
         * EventListener::__construct ( EventBase $base , callable $cb , mixed $data , int $flags , int $backlog , mixed $target )
         * Creates new connection listener associated with an event base.
         */
        $base = $this->EventBase;
        $cb = array($this, "accept_conn_cb");
        $data = [];
        $flag = EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE;
        $backlog = -1;
        $target = $this->config['websocket']['host'] . ':' . $this->config['websocket']['port'];
        $listener = new EventListener($base,$cb,$data,$flag, $backlog,$target);
        if (!$listener) {
            echo "Couldn't create listener";
            exit(1);
        }
        $listener->setErrorCallback(array($this, "listener_error_cb"));
        //挂载到当前对象
        $this->listener = $listener;
    }
    /**
     * 实现方法http的server
     */
    public function httpServer(){

        if( empty($this->EventBase) ){
            $this->EventBase = new EventBase();
        }
        $EventHttp = new \EventHttp($this->EventBase);
        $EventHttp->setAllowedMethods(\EventHttpRequest::CMD_GET | \EventHttpRequest::CMD_POST);
        $EventHttp->setDefaultCallback(function($EventHttpRequest){
            try{
                $EventHttpRequest->addHeader('Content-Type','text/html;charset=utf-8',\EventHttpRequest::OUTPUT_HEADER);
                $EventBufferr = $EventHttpRequest->getOutputBuffer();
                $query = parse_url($EventHttpRequest->getUri(), PHP_URL_QUERY);
                $request['query'] = $query;
                $msg = call_user_func_array($this->onHttpMessage,array($request));
                //safe_dump($msg);
                $EventBufferr->add( $msg );
                $EventHttpRequest->sendReply(200, "OK",$EventBufferr);
            }catch( \Exception $e ){
                ExceptionHandle::chatRenderException($e);
            }
        });
        $EventHttp->setCallback("/favicon.ico", function($EventHttpRequest){
            $EventHttpRequest->sendReplyEnd();
        });
        $EventHttp->bind($this->config['http']['host'], $this->config['http']['port']);
        $this->EventHttp = $EventHttp;
    }

    /**
     * websocket建立连接的回调
     * @param $listener EventListener的实例
     * @param $fd socket资源
     * @param $address 地址
     * @param $ctx 
     */
    public function accept_conn_cb($listener, $fd, $address, $ctx) {
        // We got a new connection! Set up a bufferevent for it. */
        //var_dump($this->EventBase);
        //var_dump($this->conn);
        //var_dump( $this->onWebsocketConnect );
        try{
            call_user_func_array($this->onWebsocketConnect,[$fd]);
            $base = $this->EventBase;
            $bev = new EventBufferEvent($base, $fd, EventBufferEvent::OPT_CLOSE_ON_FREE);
            $Connection = new Connection($bev);
            $this->Connections[(int)$fd] = $Connection;
            
            $bev->setCallbacks(array($this,'read_event_cb'),NULL,array($this,'close_conn_event_cb'),NULL);

            if (!$bev->enable(\Event::READ)) {
                echo "Failed to enable READ\n";
                return;
            }
        }catch( \Exception $e ){
            echo $e;
        }
        //safe_dump( count($this->Connections) );
    }
    /**
     * 可读的事件
     * @param EventBufferEvent $EventBufferEvent
     * @param $ctx
     */
    public function read_event_cb($EventBufferEvent,$ctx){
        $fd = $EventBufferEvent->fd;
        $Connection = $this->Connections[(int)$fd];
        $method = $Connection->substr(0,3); 
        //var_dump($method);
        //safe_dump($firstChar);
        if( $method == 'GET' ){
            try{
                //websocket握手
                $request = WebSocket::instance()->Handshake($Connection);
                //safe_dump($request);
                call_user_func_array($this->onWebsocketHandshake,[$Connection,$request,null]);
            }catch( \Exception $e ){
                $EventBufferEvent->disable(\Event::READ);
                //$EventBufferEvent->free();
                //$EventBufferEvent->close();//这行代码会导致程序挂掉
                call_user_func_array($this->onWebsocketHandshake,[$Connection,null,$e]);
            }
        }else{
            /**
             * 一次事件里面可能有多个frame，需要循环读取frame；
             * 等到返回false或者异常退出；异常退出；需要关闭事件触发
             */
            do{
                try{
                    $frame = Websocket::instance()->readFrame($Connection);
                    if( is_array($frame) ){
                        //safe_dump($frame);
                        if( $frame['opcode'] == 0x1 || $frame['opcode'] == 0x2 ){
                            call_user_func_array($this->onWebsocketMessage,[$Connection,$frame,null]);
                        }
                    }else{
                        return ;
                    }
                }catch( \Exception $e ){
                    //echo (string)$e;
                    
                    $EventBufferEvent->disable(\Event::READ);
                    //$EventBufferEvent->free();
                    //$EventBufferEvent->close();//这行代码会导致程序挂掉
                    Websocket::instance()->clearFrameByFd($Connection->fd);
                    call_user_func_array($this->onWebsocketMessage,[$Connection,null,$e]);
                    return;
                }
            }while(1);
        }
    }
    /**
     * listener错误回调
     * @param $listener EventListener的实例
     * @param $ctx
     */
    public function listener_error_cb($listener, $ctx) {
        $str = sprintf('Got an error %d (%s) on the listener. Shutting down.',EventUtil::getLastSocketErrno(),EventUtil::getLastSocketError());
        throw new \Exception($str);
        $this->EventBase->exit(NULL);
    }
    /**
     * websocket关闭连接回调
     * @param $bev
     * @param $events
     * @param $ctx
     */
    public function close_conn_event_cb($bev, $events, $ctx) {
        //var_dump($bev);
        try{
            if ($events & (EventBufferEvent::EOF | EventBufferEvent::ERROR)) {
                Websocket::instance()->clearFrameByFd($bev->fd);
                if( isset($this->Connections[$bev->fd]) ){
                    unset($this->Connections[$bev->fd]);
                }
                $fd = $bev->fd;
                $bev->free();
                if( $this->onWebsocketClose != null ){
                    call_user_func_array($this->onWebsocketClose,[$fd,null]);
                }
            }
        }catch( \Exception $e ){
            call_user_func_array($this->onWebsocketClose,[$fd,$e]);
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
            $this->EventBase = new EventBase();
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
     * 实现清除定时器
     * @param string $key 定时器key
     */
    public function clearTimer($key){
        if( isset($this->TimerEvents[$key]) ){
            $this->TimerEvents[$key]->del();
        }
    }
    public function clearAllTimer(){
        foreach( $this->TimerEvents as $TimerEvent ){
            $TimerEvent->del();
        }
    }
    /**
     * 添加信号事件
     * @param int $signal 信号
     * @param callable $callback 回调函数
     */
    public function signal($signal,callable $callback){
        $this->signals[$signal] = \Event::signal($this->EventBase,$signal,$callback);
        $this->signals[$signal]->addSignal();
    }
    /**
     * 实现开始事件循环
     */
    public function loop(){
        $this->EventBase->loop();
    }
}