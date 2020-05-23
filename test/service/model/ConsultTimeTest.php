<?php
use PHPUnit\Framework\TestCase;
use onlineChat\model\ConsultTime;
include_once __DIR__ . '/../../init.php';
class ConsultTimeTest extends TestCase{
    public function test_join(){
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
        $this->assertTrue( isset(ConsultTime::$consult_times[1]) );

    }
    public function test_del(){
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
        ConsultTime::del(1);
        $this->assertTrue( !isset(ConsultTime::$consult_times[1]) );
    }
}