<?php
use PHPUnit\Framework\TestCase;
use onlineChat\lib\websocketServer\event\Server;
use onlineChat\lib\protocol\Websocket;
use test\common\Connection;
include_once __DIR__ . '/../../../../init.php';
class ServerStock extends Server{
    public $EventBase;

    public function __construct($config){
        $this->EventBase = new EventBase();
    }
    public function setConnection($fd,$Connection){
        $this->Connections[$fd] = $Connection;
    }
}
class ServerTest extends TestCase
{
    private $Websocket;
    public function setup(){
        $this->Websocket = new Websocket();
    }
    public function test_websocketServer(){
        if( $this->isWindowsOs() ){
            $this->assertTrue(true);
            return;
        }
        $server = new Server([
            'websocket'=>[
                'host'=>'127.0.0.1',
                'port'=>2081
            ],
            'http'=>[
                'host'=>'127.0.0.1',
                'port'=>3081
            ]
        ]);
        $this->assertTrue(true); 
    }

    public function test_accept_conn_cb(){
        $server = new ServerStock(null);
        $server->on('websocketConnect',function(){
            echo "exec websocket connect";
        });
        $socket = stream_socket_server("tcp://0.0.0.0:10005", $errno, $errstr);
        /*
        while ($conn = stream_socket_accept($socket)) {
            fwrite($conn, 'The local time is ' . date('n/j/Y g:i a') . "\n");
            fclose($conn);
        }
        */
        $fp = fsockopen("127.0.0.1", 10005, $errno, $errstr, 30);
        ob_start();
        $server->accept_conn_cb(null,$fp,null,null);
        $str = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($str,'exec websocket connect');
        $this->assertTrue(true);

    }

    public function test_read_event_cb(){
        

        $server = new ServerStock(null);
        $server->on('websocketMessage',function($Connection,$request,$exception){
            if( $exception == null ){
                echo 'exec websocket message';
            }else{
                echo 'exec websocket message exception';
            }
        });
        $server->on('websocketHandshake',function($Connection,$request,$exception){
            if( $exception == null ){
                echo 'exec websocket handshake';
            }else{
                echo 'exec websocket handshake exception';
            }
        });
        $Base = new EventBase();
        $socket = stream_socket_server("tcp://0.0.0.0:10006", $errno, $errstr);
        $fp = fsockopen("127.0.0.1", 10006, $errno, $errstr, 30);
        $EventBufferEvent = new EventBufferEvent($Base,$fp);
        $Connection = new Connection($EventBufferEvent->fd);
        $server->setConnection($Connection->fd,$Connection);

        ob_start();
        $Connection->inputStream('GET');
        $server->read_event_cb($EventBufferEvent,null);
        $str = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($str,'exec websocket handshake exception');

        ob_start();
        $Connection->inputStream('GET /?access_token=486cdcdf75253a256d3b5b67ae0e6679 HTTP/1.1
        Origin: null
        Host: 127.0.0.1:2080
        Sec-WebSocket-Key: NWU1OTM5NWM4Y2IzNA==
        User-Agent: SwooleWebsocketClient/0.1.4
        Upgrade: websocket
        Connection: Upgrade
        Sec-WebSocket-Protocol: wamp
        Sec-WebSocket-Version: 13');
        $server->read_event_cb($EventBufferEvent,null);
        $str = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($str,'exec websocket handshake');

        $msg = $this->Websocket->genFrame([
            'payload-data'=>'123132',
            'masked'=>0x1
        ]);
        ob_start();
        $Connection->inputStream($msg);
        $server->read_event_cb($EventBufferEvent,null);
        $str = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($str,'exec websocket message');

        $msg = '123';
        ob_start();
        $Connection->inputStream($msg);
        $server->read_event_cb($EventBufferEvent,null);
        $str = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($str,'exec websocket message exception');
        
    }
    /**
     * @expectedException Exception
     */
    public function test_accept_error_cb(){
        $server = new ServerStock(null);
        $server->listener_error_cb(null,null);
    }
    public function test_close_conn_event_cb_EOF(){
        $socket = stream_socket_server("tcp://0.0.0.0:10007", $errno, $errstr);
        $fp = fsockopen("127.0.0.1", 10007, $errno, $errstr, 30);
        $Base = new EventBase();
        $EventBufferEvent = new EventBufferEvent($Base,$fp);
        $server = new ServerStock(null);
        $server->on('websocketClose',function(){
            echo 'callback websocketClose';
        });
        ob_start();
        $server->close_conn_event_cb($EventBufferEvent,EventBufferEvent::EOF,null);
        $str = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($str,'callback websocketClose');
    }

    public function test_close_conn_event_cb_ERROR(){

        $socket = stream_socket_server("tcp://0.0.0.0:10008", $errno, $errstr);
        $fp = fsockopen("127.0.0.1", 10008, $errno, $errstr, 30);
        $Base = new EventBase();
        $EventBufferEvent = new EventBufferEvent($Base,$fp);
        $server = new ServerStock(null);
        $server->on('websocketClose',function(){
            echo 'callback websocketClose';
        });

        ob_start();
        $server->close_conn_event_cb($EventBufferEvent,EventBufferEvent::ERROR,null);
        $str = ob_get_contents();
        ob_end_clean();
        $this->assertEquals($str,'callback websocketClose');
    }

    public function test_timer(){
        if( $this->isWindowsOs() ){
            $this->assertTrue(true);
            return;
        }
        $server = new ServerStock(null);
        $key = $server->timer(function(){},5);
        $this->assertTrue(true);
    }

    public function test_clearTimer(){
        $server = new ServerStock(null);
        $key = $server->clearTimer(uniqid());
        $this->assertTrue(true);
    }
    public function test_clearAllTimer(){
        $server = new ServerStock(null);
        $key = $server->clearAllTimer();
        $this->assertTrue(true);
    }

    public function test_signal(){
        if( $this->isWindowsOs() ){
            $this->assertTrue(true);
            return;
        }
        $server = new ServerStock(null);
        !defined('SIGTERM') && define('SIGTERM',15);
        $server->signal(SIGTERM,function(){

        });
        $this->assertTrue(true);
    }
    /**
     * 是否是windows系统
     */
    public function isWindowsOs(){
        return strtoupper(substr(PHP_OS, 0, 3)) == 'WIN';
    }
}