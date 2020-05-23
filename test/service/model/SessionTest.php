<?php
use PHPUnit\Framework\TestCase;
use onlineChat\model\Session;
use test\Common\Connection;
include_once __DIR__ . '/../../init.php';
class SessionTest extends TestCase{
    public function test_add(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $this->assertTrue( isset(Session::$sessions[$access_token]) );
    }
    public function test_setConnection(){

        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $this->assertTrue( isset(Session::$sessions[$access_token]) );

        $Connection = new Connection();
        Session::setConnection($access_token,$Connection);
        $this->assertTrue( !empty(Session::$sessions[$access_token]['connection']) );
    }
    public function test_del(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $this->assertTrue( isset(Session::$sessions[$access_token]) );

        $Connection = new Connection();
        Session::setConnection($access_token,$Connection);
        Session::del($access_token,'access_token');
        $this->assertTrue( !isset(Session::$sessions[$access_token]) );

        $Connection = new Connection();
        Session::setConnection($access_token,$Connection);
        Session::del($Connection->fd,'fd');
        $this->assertTrue( !isset(Session::$sessions[$access_token]) );

    }
    public function test_getByUid(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $sessions = Session::getByUid($uid,$tmp);
        $this->assertEquals($sessions[0],Session::$sessions[$access_token]);

        $tmp = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $sessions = Session::getByUid($uid,$tmp);
        $this->assertEquals($sessions[0],Session::$sessions[$access_token]);
    }
    public function test_isset(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $this->assertTrue(Session::isset($uid,$tmp) );
        Session::del($access_token,'access_token');
        $this->assertFalse(Session::isset($uid,$tmp) );

        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $tmp = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $this->assertTrue(Session::isset($uid,$tmp) );
        Session::del($access_token,'access_token');
        $this->assertFalse(Session::isset($uid,$tmp) );
    }
    public function test_genAccessToken(){
        $access_token1 = Session::genAccessToken();
        $access_token2 = Session::genAccessToken();
        $this->assertNotEquals($access_token1,$access_token2);
    }
    public function test_writeFrameByUid(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = mt_rand(0,1);
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $Connection = new Connection();
        Session::setConnection($access_token,$Connection);
        $msg = [
            'topic'=>'test',
            'msg'=>[
                'msg'=>'123'
            ]
        ];
        Session::writeFrameByUid($uid,$tmp,$msg);
        $buffer = $Connection->outStream(1000);
        $this->assertTrue( strlen($buffer) > 0  );
    }
    public function test_writeFrameByFd(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = mt_rand(0,1);
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $Connection = new Connection();
        Session::setConnection($access_token,$Connection);
        $msg = [
            'topic'=>'test',
            'msg'=>[
                'msg'=>'123'
            ]
        ];
        Session::writeFrameByFd($Connection->fd,$msg);
        $buffer = $Connection->outStream(1000);
        $this->assertTrue( strlen($buffer) > 0  );
    }
    public function test_writeFrameByAccessToken(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = mt_rand(0,1);
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $Connection = new Connection();
        Session::setConnection($access_token,$Connection);
        $msg = [
            'topic'=>'test',
            'msg'=>[
                'msg'=>'123'
            ]
        ];
        Session::writeFrameByAccessToken($access_token,$msg);
        $buffer = $Connection->outStream(1000);
        $this->assertTrue( strlen($buffer) > 0  );
    }
}