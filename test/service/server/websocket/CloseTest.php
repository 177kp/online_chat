<?php
use PHPUnit\Framework\TestCase;
use onlineChat\server\websocket\Close;
use onlineChat\model\Session;
use test\Common\Connection;
include_once __DIR__ . '/../../../init.php';
class CloseTest extends TestCase{
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
        Session::setConnection($access_token,$Connection);

        Close::callback($Connection->fd);
        $this->assertTrue( !isset(Session::$sessions[$access_token]) );
    }
}