<?php
use PHPUnit\Framework\TestCase;
use onlineChat\lib\protocol\Websocket;
use test\common\Connection;
include_once __DIR__ . '/../../../init.php';
class WebsocketTest extends TestCase
{
    private $Connection;
    private $Websocket;
    public function setup(){
        $this->Connection = new Connection();
        $this->Websocket = new Websocket();
        //$this->Connection->inputStream('123');
        //$this->Connection->read(1);exit;
    }
    
    public function testHandshake()
    {

        $str = 'GET /?access_token=486cdcdf75253a256d3b5b67ae0e6679 HTTP/1.1
        Origin: null
        Host: 127.0.0.1:2080
        Sec-WebSocket-Key: NWU1OTM5NWM4Y2IzNA==
        User-Agent: SwooleWebsocketClient/0.1.4
        Upgrade: websocket
        Connection: Upgrade
        Sec-WebSocket-Protocol: wamp
        Sec-WebSocket-Version: 13';
        $this->Connection->inputStream($str);
        $request = $this->Websocket->Handshake($this->Connection);
        $this->assertArrayHasKey('uri',$request);
        $this->assertArrayHasKey('method',$request);
        $this->assertArrayHasKey('protocol',$request);
        $this->assertArrayHasKey('Origin',$request);
        $this->assertArrayHasKey('Host',$request);
        $this->assertArrayHasKey('Sec-WebSocket-Key',$request);
        $this->assertArrayHasKey('Sec-WebSocket-Protocol',$request);
        $this->assertArrayHasKey('Sec-WebSocket-Version',$request);

    }
    /**
     * @expectedException Exception
     */
    public function testHandshakeException_method(){
        $str = 'GET1 ';
        $this->Connection->inputStream($str);
        $request = $this->Websocket->Handshake($this->Connection);
    }
    /**
     * @expectedException Exception
     */
    public function testHandshakeException_uri(){
        $str = 'GET  ';
        $this->Connection->inputStream($str);
        $request = $this->Websocket->Handshake($this->Connection);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage 协议版本不正确！
     */
    public function testHandshakeException_protocol(){
        $str = 'GET /?access_token=486cdcdf75253a256d3b5b67ae0e6679 HTTP/1.0';
        $this->Connection->inputStream($str);
        $request = $this->Websocket->Handshake($this->Connection);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Connection字段不正确！
     */
    public function testHandshakeException_Connection(){
        $str = 'GET /?access_token=486cdcdf75253a256d3b5b67ae0e6679 HTTP/1.1
        Origin: null
        Host: 127.0.0.1:2080
        Sec-WebSocket-Key: NWU1OTM5NWM4Y2IzNA==
        User-Agent: SwooleWebsocketClient/0.1.4
        Upgrade: websocket
        Connection: Upgrade1
        Sec-WebSocket-Protocol: wamp
        Sec-WebSocket-Version: 13';
        $this->Connection->inputStream($str);
        $request = $this->Websocket->Handshake($this->Connection);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Upgrade字段不正确！
     */
    public function testHandshakeException_Upgrade(){
        $str = 'GET /?access_token=486cdcdf75253a256d3b5b67ae0e6679 HTTP/1.1
        Origin: null
        Host: 127.0.0.1:2080
        Sec-WebSocket-Key: NWU1OTM5NWM4Y2IzNA==
        User-Agent: SwooleWebsocketClient/0.1.4
        Upgrade: websocket1
        Connection: Upgrade
        Sec-WebSocket-Protocol: wamp
        Sec-WebSocket-Version: 13';
        $this->Connection->inputStream($str);
        $request = $this->Websocket->Handshake($this->Connection);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Sec-WebSocket-Version字段不正确！
     */
    public function testHandshakeException_Sec_WebSocket_Version(){
        $str = 'GET /?access_token=486cdcdf75253a256d3b5b67ae0e6679 HTTP/1.1
        Origin: null
        Host: 127.0.0.1:2080
        Sec-WebSocket-Key: NWU1OTM5NWM4Y2IzNA==
        User-Agent: SwooleWebsocketClient/0.1.4
        Upgrade: websocket
        Connection: Upgrade
        Sec-WebSocket-Protocol: wamp
        Sec-WebSocket-Version: 14';
        $this->Connection->inputStream($str);
        $request = $this->Websocket->Handshake($this->Connection);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Sec-WebSocket-Key字段不存在！
     */
    public function testHandshakeException_Sec_WebSocket_Key(){
        $str = 'GET /?access_token=486cdcdf75253a256d3b5b67ae0e6679 HTTP/1.1
        Origin: null
        Host: 127.0.0.1:2080
        User-Agent: SwooleWebsocketClient/0.1.4
        Upgrade: websocket
        Connection: Upgrade
        Sec-WebSocket-Protocol: wamp
        Sec-WebSocket-Version: 13';
        $this->Connection->inputStream($str);
        $request = $this->Websocket->Handshake($this->Connection);
    }
    public function test_server_ReadFrame_payload_length(){
        $data = $this->getRandStr(100);
        $msg = $this->Websocket->genFrame([
            'payload-data'=>$data,
            'masked'=>0x1
        ]);
        $this->Connection->inputStream($msg);
        $recvMsg = $this->Websocket->readFrame($this->Connection);
        $this->assertArrayHasKey('fin',$recvMsg);
        $this->assertArrayHasKey('rsv1',$recvMsg);
        $this->assertArrayHasKey('rsv2',$recvMsg);
        $this->assertArrayHasKey('rsv3',$recvMsg);
        $this->assertArrayHasKey('opcode',$recvMsg);
        $this->assertArrayHasKey('masked',$recvMsg);
        $this->assertArrayHasKey('payload-length',$recvMsg);
        $this->assertArrayHasKey('masking-key',$recvMsg);
        $this->assertArrayHasKey('payload-data',$recvMsg);
        $this->assertEquals($data,$recvMsg['payload-data']);
    }
    public function test_server_ReadFrame_payload_length_16(){
        $data = $this->getRandStr(1000);
        $msg = $this->Websocket->genFrame([
            'payload-data'=>$data,
            'masked'=>0x1
        ]);
        $this->Connection->inputStream($msg);
        $recvMsg = $this->Websocket->readFrame($this->Connection);
        $this->assertArrayHasKey('fin',$recvMsg);
        $this->assertArrayHasKey('rsv1',$recvMsg);
        $this->assertArrayHasKey('rsv2',$recvMsg);
        $this->assertArrayHasKey('rsv3',$recvMsg);
        $this->assertArrayHasKey('opcode',$recvMsg);
        $this->assertArrayHasKey('masked',$recvMsg);
        $this->assertArrayHasKey('payload-length',$recvMsg);
        $this->assertArrayHasKey('payload-length-16',$recvMsg);
        $this->assertArrayHasKey('masking-key',$recvMsg);
        $this->assertArrayHasKey('payload-data',$recvMsg);
        $this->assertEquals($data,$recvMsg['payload-data']);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage payload-length-63超过65535！
     */
    public function test_server_ReadFrame_payload_length_64(){
        $data = $this->getRandStr(100000);
        //var_dump($data);
        $msg = $this->Websocket->genFrame([
            'payload-data'=>$data
        ]);
        $this->Connection->inputStream($msg);
        $recvMsg = $this->Websocket->readFrame($this->Connection);
    }
    public function test_client_ReadFrame_payload_length(){
        $data = $this->getRandStr(100);
        $msg = $this->Websocket->genFrame([
            'payload-data'=>$data
        ]);
        $this->Connection->inputStream($msg);
        $recvMsg = $this->Websocket->readFrame($this->Connection);
        $this->assertArrayHasKey('fin',$recvMsg);
        $this->assertArrayHasKey('rsv1',$recvMsg);
        $this->assertArrayHasKey('rsv2',$recvMsg);
        $this->assertArrayHasKey('rsv3',$recvMsg);
        $this->assertArrayHasKey('opcode',$recvMsg);
        $this->assertArrayHasKey('masked',$recvMsg);
        $this->assertArrayHasKey('payload-length',$recvMsg);
        $this->assertArrayHasKey('payload-data',$recvMsg);
        $this->assertEquals($data,$recvMsg['payload-data']);
    }
    public function test_client_ReadFrame_payload_length_16(){
        $data = $this->getRandStr(1000);
        $msg = $this->Websocket->genFrame([
            'payload-data'=>$data
        ]);
        $this->Connection->inputStream($msg);
        $recvMsg = $this->Websocket->readFrame($this->Connection);
        $this->assertArrayHasKey('fin',$recvMsg);
        $this->assertArrayHasKey('rsv1',$recvMsg);
        $this->assertArrayHasKey('rsv2',$recvMsg);
        $this->assertArrayHasKey('rsv3',$recvMsg);
        $this->assertArrayHasKey('opcode',$recvMsg);
        $this->assertArrayHasKey('masked',$recvMsg);
        $this->assertArrayHasKey('payload-length',$recvMsg);
        $this->assertArrayHasKey('payload-length-16',$recvMsg);
        $this->assertArrayHasKey('payload-data',$recvMsg);
        $this->assertEquals($data,$recvMsg['payload-data']);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionMessage payload-length-63超过65535！
     */
    public function test_client_ReadFrame_payload_length_64(){
        $data = $this->getRandStr(100000);
        $msg = $this->Websocket->genFrame([
            'payload-data'=>$data,
            'masked'=>0x1
        ]);
        $this->Connection->inputStream($msg);
        $recvMsg = $this->Websocket->readFrame($this->Connection);
    }
    public function test_server_readFrame_packet_splicing_0(){
        
        $data1 = $this->getRandStr(123);
        $msg1 = $this->Websocket->genFrame([
            'payload-data'=>$data1,
            'masked'=>0x1
        ]);
        $data2 = $this->getRandStr(123);
        $msg2 = $this->Websocket->genFrame([
            'payload-data'=>$data2,
            'masked'=>0x1
        ]);
        $this->Connection->inputStream($msg1 . $msg2);
        $recvMsg1 = $this->Websocket->readFrame($this->Connection);
        $recvMsg2 = $this->Websocket->readFrame($this->Connection);
        $this->assertEquals($data1,$recvMsg1['payload-data']);
        $this->assertEquals($data2,$recvMsg2['payload-data']);

    }

    public function test_server_readFrame_packet_splicing_1(){
        
        $data1 = $this->getRandStr(123);
        //var_dump($data1);exit;
        $msg1 = $this->Websocket->genFrame([
            'payload-data'=>$data1,
            'masked'=>0x1
        ]);
        $data2 = $this->getRandStr(123);
        $msg2 = $this->Websocket->genFrame([
            'payload-data'=>$data2,
            'masked'=>0x1
        ]);

        $sendData = $msg1.$msg2;
        //var_dump(strlen($sendData),strlen($msg1),strlen($msg2));
        $sendLength = mt_rand(1,strlen($msg1)-10 );
        $sendData1 = substr($sendData,0,$sendLength);
        $sendData2 = substr($sendData,strlen($sendData1));

        $this->Connection->inputStream( $sendData1  );
        $recvMsg1 = $this->Websocket->readFrame($this->Connection);
        //var_dump($recvMsg1);exit;
        if( $recvMsg1 == null ){
            $this->Connection->inputStream( $sendData2 );
            $recvMsg1 = $this->Websocket->readFrame($this->Connection);
        }
        $recvMsg2 = $this->Websocket->readFrame($this->Connection);
        //var_dump( $recvMsg2 );exit;
        //var_dump(strlen($data1),strlen($recvMsg1['payload-data']) );exit;
        $this->assertEquals($data1,$recvMsg1['payload-data']);
        $this->assertEquals($data2,$recvMsg2['payload-data']);

    }
    public function test_client_readFrame_packet_splicing_0(){
        
        $data1 = $this->getRandStr(123);
        $msg1 = $this->Websocket->genFrame([
            'payload-data'=>$data1,
            'masked'=>0x0
        ]);
        $data2 = $this->getRandStr(123);
        $msg2 = $this->Websocket->genFrame([
            'payload-data'=>$data2,
            'masked'=>0x0
        ]);
        $this->Connection->inputStream($msg1 . $msg2);
        $recvMsg1 = $this->Websocket->readFrame($this->Connection);
        $recvMsg2 = $this->Websocket->readFrame($this->Connection);
        $this->assertEquals($data1,$recvMsg1['payload-data']);
        $this->assertEquals($data2,$recvMsg2['payload-data']);

    }
    public function test_client_readFrame_packet_splicing_1(){
        
        $data1 = $this->getRandStr(123);
        //var_dump($data1);exit;
        $msg1 = $this->Websocket->genFrame([
            'payload-data'=>$data1,
            'masked'=>0x0
        ]);
        $data2 = $this->getRandStr(123);
        $msg2 = $this->Websocket->genFrame([
            'payload-data'=>$data2,
            'masked'=>0x0
        ]);

        $sendData = $msg1.$msg2;
        //var_dump(strlen($sendData),strlen($msg1),strlen($msg2));
        $sendLength = mt_rand(1,strlen($msg1)-10 );
        $sendData1 = substr($sendData,0,$sendLength);
        $sendData2 = substr($sendData,strlen($sendData1));

        $this->Connection->inputStream( $sendData1  );
        $recvMsg1 = $this->Websocket->readFrame($this->Connection);
        //var_dump($recvMsg1);exit;
        if( $recvMsg1 == null ){
            $this->Connection->inputStream( $sendData2 );
            $recvMsg1 = $this->Websocket->readFrame($this->Connection);
        }
        $recvMsg2 = $this->Websocket->readFrame($this->Connection);
        //var_dump( $recvMsg2 );exit;
        //var_dump(strlen($data1),strlen($recvMsg1['payload-data']) );exit;
        $this->assertEquals($data1,$recvMsg1['payload-data']);
        $this->assertEquals($data2,$recvMsg2['payload-data']);

    }
    public function test_genHandShakeRequest(){
        $randKey = uniqid();
        $request = $this->Websocket->genHandShakeRequest('127.0.0.1','1234','/abc?id=1',$randKey);
        $this->Connection->inputStream($request);
        $req = $this->Websocket->HandShake($this->Connection);

        $this->assertEquals($req['Host'],'127.0.0.1');
        $this->assertEquals($req['uri'],'/abc?id=1');
        $this->assertEquals($req['Sec-WebSocket-Key'],$randKey);
    }

    public function test_verifyUpgrade(){
        $randKey = uniqid();
        $request = $this->Websocket->genHandShakeRequest('127.0.0.1','1234','/abc?id=1',$randKey);
        $this->Connection->inputStream($request);
        $this->Websocket->HandShake($this->Connection);
        $handle = $this->Connection->outStream(5000);
        $response = $this->Websocket->verifyUpgrade($handle,$randKey);
        $sec_websocket_key = base64_encode(sha1($randKey . Websocket::GUID , true));
        $this->assertEquals($response['Sec-WebSocket-Accept'],$sec_websocket_key);
    }

    private function getRandStr($length){
        $str = file_get_contents(__FILE__);
        $strLength = strlen($str);
        $res = '';
        for( $i=0;$i<$length;$i++ ){
            $res .= substr($str,mt_rand(0,$strLength),1);
        }
        return $res;
    }
}

