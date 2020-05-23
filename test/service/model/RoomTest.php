<?php
use PHPUnit\Framework\TestCase;
use onlineChat\model\Room;
include_once __DIR__ . '/../../init.php';
class RoomTest extends TestCase{
    public function test_set(){
        $uids = [1,2,3];
        Room::set(1,$uids);
        $this->assertTrue( isset(Room::$rooms[1]) );
    }
    public function test_join(){
        $uid = mt_rand(1,10000);
        Room::join(1,$uid);
        $this->assertTrue( in_array($uid,Room::$rooms[1]) );
    }
    public function test_del(){
        $uid = mt_rand(1,10000);
        Room::join(1,$uid);
        Room::del(1,$uid);
        $this->assertTrue( !in_array($uid,Room::$rooms[1]) );
    }
}