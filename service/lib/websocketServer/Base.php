<?php
namespace onlineChat\lib\websocketServer;
/**
 * websocket的server抽象类
 */
abstract class Base{
    /**
     * @var $config server配置
     * [
     *     websocket=>[
     *         host=>主机地址
     *         port=>端口号
     *     ],
     *     http=>[
     *         host=>主机地址
     *         port=>端口号
     *    ]
     * ]
     */
    protected $config;
    /**
     * @var $onWebsocketConnect websocket建立连接回调
     */
    protected $onWebsocketConnect;
    /**
     * @var $onWebsocketHandshake websocket握手之后回调
     */
    protected $onWebsocketHandshake;
    /**
     * @var $onWebsocketMessage websocket收到消息回调
     */
    protected $onWebsocketMessage;
    /**
     * @var $onWebsocketClose websocket关闭连接回调
     */
    protected $onWebsocketClose;
    /**
     * @var $onHttpMessage http收到消息回调
     */
    protected $onHttpMessage;
    /**
     * 实例化方法
     * @param $config 参考当前对象的$config
     */
    public function __construct($config){
        if( empty($config['websocket']) ){
            throw new \Exeption('websocket配置不存在！');
        }
        if( empty($config['websocket']['host']) ){
            throw new \Exeption('websocket[host]不存在！');
        }
        if( empty($config['websocket']['port']) ){
            throw new \Exeption('websocket[port]不存在！');
        }
        if( empty($config['http']) ){
            throw new \Exeption('http配置不存在！');
        }
        if( empty($config['http']['host']) ){
            throw new \Exeption('http[host]不存在！');
        }
        if( empty($config['http']['port']) ){
            throw new \Exeption('http[port]不存在！');
        }
        $this->config = $config;
        $this->onWebsocketConnect = function(){
            //$this->safe_dump('on websocket connect');
        };
        $this->onWebsocketHandshake = function(){
            $this->safe_dump('on websocket handshake');
        };
        $this->onWebsocketMessage = function(){
            $this->safe_dump('on websocket message');
        };
        $this->onWebsocketClose = function(){
            $this->safe_dump('on websocket close');
        };
        $this->onHttpMessage = function(){
            $this->safe_dump('on http message');
        };
        $this->websocketServer();
        $this->httpServer();
    }
    /**
     * 绑定回调方法
     * @param string $name 回调方法名称；回调方法名称有websocketConnect、websocketHandshake、websocketMessage、websocketMessage、websocketClose、httpMessage
     * @param callable $callback 可回调
     */
    public function on($name,callable $callback){
        if( !in_array($name,['websocketConnect','websocketHandshake','websocketMessage','websocketClose','httpMessage']) ){
            throw new \Exception('name不正确！');
        }
        if( $name == 'websocketConnect' ){
            $this->onWebsocketConnect = $callback;
        }elseif( $name == 'websocketHandshake' ){
            $this->onWebsocketHandshake = $callback;
        }elseif( $name == 'websocketMessage' ){
            $this->onWebsocketMessage = $callback;
            //$callback();
        }elseif( $name == 'websocketClose' ){
            $this->onWebsocketClose = $callback;
        }elseif( $name == 'httpMessage' ){
            $this->onHttpMessage = $callback;
        }
    }
   /**
    * 安全打印，用于命令行程序，有终端的情况才打印
    */
    protected function safe_dump($var){
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
    /**
     * 抽象方法websocket的server
     */
    abstract public function websocketServer();
    /**
     * 抽象方法http的server
     */
    abstract public function httpServer();
    /**
     * 抽象方法定时器
     * @param callable $callback 可回调
     * @param int $interval 时间间隔，单位秒
     * @return string 定时器的key
     */
    abstract public function timer(callable $callback,$interval);
    /**
     * 抽象方法清除定时器
     * @param string $key 定时器key
     */
    abstract public function clearTimer($key);
    /**
     * 抽象方法开始事件循环
     */
    abstract public function loop();
}