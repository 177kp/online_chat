<?php
use PHPUnit\Framework\TestCase;
use onlineChat\server\websocket\Handshake;
use onlineChat\model\Session;
use test\Common\Connection;
include_once __DIR__ . '/../../../init.php';
class HandshakeTest extends TestCase{
    public function test_callback(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);

        $Connection = new Connection();

        $request = [
            'uri'=>'/?' . http_build_query(['access_token'=>$access_token])
        ];
        $res = Handshake::callback($Connection,$request);
        //var_dump($Connection->outStream(1000));
        $this->assertTrue( !empty(Session::$sessions[$access_token]['connection']) );
    }
}