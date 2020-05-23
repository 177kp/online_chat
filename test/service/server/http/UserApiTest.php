<?php
use PHPUnit\Framework\TestCase;
use onlineChat\server\http\UserApi;
use onlineChat\model\Session;
include_once __DIR__ . '/../../../init.php';
class UserApiTest extends TestCase{
    public function test_get_access_token(){
        $get = [
            'uid'=>mt_rand(1,10000),
            'name'=>'1231',
            'head_img'=>'123',
            'tmp'=>0,
            'user_type'=>0,
            'to_id'=>'',
            'sessions'=>[
                'to_id'=>1,
                'chat_type'=>1,
                'tmp'=>1
            ]
        ];
        $res = UserApi::get_access_token($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $this->assertTrue( is_array(Session::getByUid($get['uid'],$get['tmp'])) );

        $get = [
            'uid'=>mt_rand(1,10000),
            'name'=>'1231',
            'head_img'=>'123',
            'tmp'=>0,
            'user_type'=>1,
            'to_id'=>'',
            'sessions'=>[
                'to_id'=>1,
                'chat_type'=>1,
                'tmp'=>1
            ]
        ];
        $res = UserApi::get_access_token($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $this->assertTrue( is_array(Session::getByUid($get['uid'],$get['tmp'])) );
    }
    public function test_userinfo(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);

        $get = [
            'access_token'=>$access_token
        ];
        $res = UserApi::userinfo($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $this->assertEquals($res['data']['uid'],$uid);
    }
}