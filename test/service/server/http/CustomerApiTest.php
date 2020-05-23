<?php
use PHPUnit\Framework\TestCase;
use onlineChat\server\http\CustomerApi;
use onlineChat\model\Session;
include_once __DIR__ . '/../../../init.php';
class CustomerApiTest extends TestCase{
    public function test_getCustomerContacts(){

        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 1;
        $to_id = '';
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);

        $get = [];
        $res = CustomerApi::getCustomerContacts($get);
        //var_dump($res);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $this->assertTrue(count($res['data'])>0);
    }
    public function test_customerJoin(){

        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 1;
        $to_id = '';
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);

        $access_token1 = uniqid();
        $uid1 = mt_rand(10001,20000);
        $user_type1 = 1;
        $name1 = '123123';
        $head_img1 = 'head_img';
        $sessions1 = [1,2,3];
        $tmp1 = 0;
        $to_id1 = null;
        Session::add($access_token1,$uid1,$user_type1,$name1,$head_img1,$sessions1,$tmp1,$to_id1);

        $get = [
            'to_id'=>$uid,
            'uid'=>$uid1
        ];
        $res = CustomerApi::customerJoin($get);
        //var_dump($res);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $this->assertTrue( Session::$sessions[$access_token]['to_id'] != "" );
       
    }
}