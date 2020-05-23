<?php
use PHPUnit\Framework\TestCase;
use onlineChat\lib\websocketServer\event\Connection;
class ConnectionTest extends TestCase{
    public function test_read(){
        $socket = stream_socket_server("tcp://0.0.0.0:11005", $errno, $errstr);
        $fp = fsockopen("127.0.0.1", 11005, $errno, $errstr, 30);
        $Base = new EventBase();
        $EventBufferEvent = new EventBufferEvent($Base,$fp);
        $EventBufferEvent->input->prepend('1234567890');
        $Connection = new Connection($EventBufferEvent);
        $str = $Connection->read(10);
        $this->assertEquals($str,'1234567890');
    }
    public function test_write(){
        $socket = stream_socket_server("tcp://0.0.0.0:11006", $errno, $errstr);
        $fp = fsockopen("127.0.0.1", 11006, $errno, $errstr, 30);

        //$conn = stream_socket_accept($socket);

        $Base = new EventBase();
        $EventBufferEvent = new EventBufferEvent($Base,$fp);
        $Connection = new Connection($EventBufferEvent);
        $Connection->write('1234567890');
        $this->assertTrue(true);
    }
    public function test_substr(){

        $socket = stream_socket_server("tcp://0.0.0.0:11007", $errno, $errstr);
        $fp = fsockopen("127.0.0.1", 11007, $errno, $errstr, 30);
        $Base = new EventBase();
        $EventBufferEvent = new EventBufferEvent($Base,$fp);
        $EventBufferEvent->input->prepend('1234567890');
        $Connection = new Connection($EventBufferEvent);
        $str = $Connection->substr(0,1);
        $this->assertEquals($str,'1');
        
    }
}