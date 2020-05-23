<?php
use PHPUnit\Framework\TestCase;
use onlineChat\model\Message;
use onlineChat\model\ConsultTime;
include_once __DIR__ . '/../../init.php';
class MessageTest extends TestCase{
    public function test_1(){
        $uid =1;
        $chat_type=1;
        $to_id=2;
        $msg_type=0;
        $msg="message";
        $ctime=time();
        $tmp = 0;
        Message::genMessage($uid,$chat_type,$to_id,$msg_type,$msg,$ctime=null);
        Message::genOnlineMessage($uid,$tmp,$chat_type);
        Message::genOfflineMessage($uid,$tmp,$chat_type);
        Message::genHeartBeatMessage([$uid]);

        ConsultTime::join([
            'id'=>1,
            'uid'=>1,
            'to_id'=>2,
            'duration_count'=>1000,
            'free_duration_count'=>100,
            'total_duration'=>1000,
            'delayed_duration_total'=>1000,
            'delayed_num'=>0,
            'status'=>0
        ]);
        Message::genConsultTimeMessage(1);
        Message::genConsultNotStartMessage($uid,$to_id,$msg);
        Message::genSystemMessage($uid,$to_id,$msg);
        Message::genCustomerNewUser($uid,$msg);
        Message::genCustomerJoin($uid,$to_id);
        Message::genWaitCustomerJoin($uid);
        Message::genServerInfo($msg);
        Message::genErrorMessage(100,$msg);
        $this->assertTrue(true);
    }
}
