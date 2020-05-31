<?php
use PHPUnit\Framework\TestCase;
use onlineChat\server\http\ChatRoomApi;
use onlineChat\model\Room;
include_once __DIR__ . '/../../../init.php';
class ChatRoomApiTest extends TestCase{
    public function test_join(){
        $get = [
            'rid'=>1,
            'uid'=>1
        ];
        $res = ChatRoomApi::join($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $this->assertTrue( isset(Room::$rooms[$get['rid']]) );
    }
    public function test_exit(){
        $get = [
            'rid'=>1,
            'uid'=>1
        ];
        $res = ChatRoomApi::signOut($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $this->assertTrue( !in_array( $get['uid'],Room::$rooms[$get['rid']] ) );
    }
}