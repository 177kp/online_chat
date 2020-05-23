<?php
use PHPUnit\Framework\TestCase;
use onlineChat\model\Publisher;
include_once __DIR__ . '/../../init.php';
class PublisherTest extends TestCase{
    private $Publisher;
    public function setup(){
        $this->Publisher = new Publisher();
    }
    public function test_addSubscriber(){
        $this->Publisher->addSubscriber([
            'addr'=>[
                'host'=>'127.0.0.1',
                'port'=>10001
            ],
            'topics'=>[
                [
                    'name'=>'message',
                    'conditions'=>[]
                ],
                [
                    'name'=>'all',
                    'conditions'=>[]
                ],
                [
                    'name'=>'offline',
                    'conditions'=>[]
                ]
            ]
        ]);
        $this->assertTrue(true);
    }
    public function test_publish(){

        $this->Publisher->addSubscriber([
            'addr'=>[
                'host'=>'127.0.0.1',
                'port'=>12345
            ],
            'topics'=>[
                [
                    'name'=>'message',
                    'conditions'=>[]
                ],
                [
                    'name'=>'all',
                    'conditions'=>[]
                ],
                [
                    'name'=>'offline',
                    'conditions'=>[]
                ]
            ]
        ]);

        $socket = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
        socket_bind($socket,'127.0.0.1',12345);
        $msg = '123';
        $this->Publisher->publish([
            'topic'=>'message',
            'msg'=>[
                'msg'=>$msg
            ]
        ]);
        socket_recvfrom($socket,$buffer,1024,0,$ip,$port);
        $data = json_decode($buffer,true);
        $this->assertEquals($data['msg']['msg'],$msg);

    }

    public function test_publish_condition_uid(){
        $port =12346;
        $uid = mt_rand(1,10000);
        $this->Publisher->addSubscriber([
            'addr'=>[
                'host'=>'127.0.0.1',
                'port'=>$port
            ],
            'topics'=>[
                [
                    'name'=>'message',
                    'conditions'=>[
                        'uid'=>$uid
                    ]
                ]
            ]
        ]);

        $socket = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
        socket_bind($socket,'127.0.0.1',$port);
        socket_set_nonblock($socket);
        $msg = '123';
        $this->Publisher->publish([
            'topic'=>'message',
            'msg'=>[
                'msg'=>$msg,
                'uid'=>10001
            ]
        ]);
        socket_recvfrom($socket,$buffer,1024,0,$ip,$port);
        $this->assertEquals($buffer,'');

        $this->Publisher->publish([
            'topic'=>'message',
            'msg'=>[
                'msg'=>$msg,
                'uid'=>$uid
            ]
        ]);
        socket_recvfrom($socket,$buffer,1024,0,$ip,$port);
        $this->assertTrue( strlen($buffer) > 0 );
        
    }

    public function test_publish_condition_room_id(){
        $port =12347;
        $room_id = mt_rand(1,10000);
        $this->Publisher->addSubscriber([
            'addr'=>[
                'host'=>'127.0.0.1',
                'port'=>$port
            ],
            'topics'=>[
                [
                    'name'=>'message',
                    'conditions'=>[
                        'room_id'=>$room_id
                    ]
                ]
            ]
        ]);

        $socket = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
        socket_bind($socket,'127.0.0.1',$port);
        socket_set_nonblock($socket);
        $msg = '123';
        
        $this->Publisher->publish([
            'topic'=>'message',
            'msg'=>[
                'msg'=>$msg,
                'to_id'=>10001,
                'chat_type'=>1
            ]
        ]);
        socket_recvfrom($socket,$buffer,1024,0,$ip,$port);
        $this->assertEquals($buffer,'');

        $this->Publisher->publish([
            'topic'=>'message',
            'msg'=>[
                'msg'=>$msg,
                'to_id'=>10001,
                'chat_type'=>0
            ]
        ]);
        socket_recvfrom($socket,$buffer,1024,0,$ip,$port);
        $this->assertEquals($buffer,'');

        $this->Publisher->publish([
            'topic'=>'message',
            'msg'=>[
                'msg'=>$msg,
                'to_id'=>$room_id,
                'chat_type'=>1
            ]
        ]);
        socket_recvfrom($socket,$buffer,1024,0,$ip,$port);
        $this->assertTrue( strlen($buffer) > 0 );
        
    }
}