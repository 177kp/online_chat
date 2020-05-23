<?php
use PHPUnit\Framework\TestCase;
use onlineChat\server\websocket\Message;
use onlineChat\model\Session;
use test\Common\Connection;
use onlineChat\model\Room;
include_once __DIR__ . '/../../../init.php';
class Message1Test extends TestCase{
    public function test_callback_chat_type_chat(){
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

        $msg = uniqid();
        $frame = [
            'payload-data'=>json_encode([
                'access_token'=>$access_token,
                'to_id'=>1,
                'chat_type'=>0,
                'msg'=>$msg,
                'msg_type'=>0
            ])
        ];
        Message::callback($Connection,$frame);
        $str = $Connection->outStream(1000);
        $str = strstr($str,$msg);
        $this->assertTrue( strlen($str)>0 );
        
    }
    public function test_callback_chat_type_room(){
        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 0;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = '1';
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $Connection = new Connection();
        Session::setConnection($access_token,$Connection);

        Room::set(1,[$uid]);
        $msg = uniqid();
        $frame = [
            'payload-data'=>json_encode([
                'access_token'=>$access_token,
                'to_id'=>1,
                'chat_type'=>1,
                'msg'=>$msg,
                'msg_type'=>0
            ])
        ];
        Message::callback($Connection,$frame);
        $str = $Connection->outStream(1000);
        //var_dump($str);
        $str = strstr($str,$msg);
        $this->assertTrue( strlen($str)>0 );
        
    }
    public function test_callback_chat_type_customer(){
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

        $msg = uniqid();
        $frame = [
            'payload-data'=>json_encode([
                'access_token'=>$access_token,
                'to_id'=>1,
                'chat_type'=>2,
                'msg'=>$msg,
                'msg_type'=>0
            ])
        ];
        Message::callback($Connection,$frame);
        $str = $Connection->outStream(1000);
        $str = strstr($str,$msg);
        $this->assertTrue( strlen($str)>0 );
        
    }
    public function test_callback_chat_type_consult(){

        $access_token = uniqid();
        $uid = mt_rand(1,10000);
        $user_type = 2;
        $name = '123123';
        $head_img = 'head_img';
        $sessions = [1,2,3];
        $tmp = 0;
        $to_id = 1;
        Session::add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id);
        $Connection = new Connection();
        Session::setConnection($access_token,$Connection);

        $msg = uniqid();
        $frame = [
            'payload-data'=>json_encode([
                'access_token'=>$access_token,
                'to_id'=>1,
                'chat_type'=>3,
                'msg'=>$msg,
                'msg_type'=>0
            ])
        ];
        Message::callback($Connection,$frame);
        $str = $Connection->outStream(1000);
        //var_export($str);
        $str = strstr($str,'consult_not_start');
        $this->assertTrue( strlen($str)>0 );
        
    }
}