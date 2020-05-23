<?php
use PHPUnit\Framework\TestCase;
use onlineChat\server\http\ConsultTimeApi;
use onlineChat\model\ConsultTime;
include_once __DIR__ . '/../../../init.php';
class ConsultTimeApiTest extends TestCase{

    public function setup(){
        ConsultTime::$consult_times = [];
    }
    public function test_start_consult(){
        $get = [
            'consult_time'=>[
                'id'=>mt_rand(1,10000),
                'uid'=>mt_rand(1,10000),
                'to_id'=>mt_rand(1,10000),
                'duration_count'=>1800,
                'free_duration_count'=>300,
                'total_duration'=>1800,
                'delayed_duration_total'=>0,
                'delayed_num'=>0,
                'status'=>0
            ]
        ];
        $res = ConsultTimeApi::start_consult($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $this->assertTrue( isset(ConsultTime::$consult_times[$get['consult_time']['id']]) );
    }
    public function test_suspend_consult(){
        $consult_time = [
            'id'=>mt_rand(1,10000),
            'uid'=>mt_rand(1,10000),
            'to_id'=>mt_rand(1,10000),
            'duration_count'=>1800,
            'free_duration_count'=>300,
            'total_duration'=>1800,
            'delayed_duration_total'=>0,
            'delayed_num'=>0,
            'status'=>0
        ];
        ConsultTime::join($consult_time);
        $get = [
            'consult_time_id'=>$consult_time['id']
        ];
        $res = ConsultTimeApi::suspend_consult($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);

        $this->assertFalse( isset( ConsultTime::$consult_times[$consult_time['id']] ) );
    }
    public function test_delayed_duration(){
        $consult_time = [
            'id'=>mt_rand(1,10000),
            'uid'=>mt_rand(1,10000),
            'to_id'=>mt_rand(1,10000),
            'duration_count'=>1800,
            'free_duration_count'=>300,
            'total_duration'=>1800,
            'delayed_duration_total'=>0,
            'delayed_num'=>0,
            'status'=>1
        ];
        ConsultTime::join($consult_time);
        $get = [
            'consult_time_id'=>$consult_time['id'],
            'duration'=>100,
        ];
        $res = ConsultTimeApi::delayed_duration($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        //var_dump( ConsultTime::$consult_times,$consult_time );
        $this->assertEquals( ConsultTime::$consult_times[$consult_time['id']]['duration_count'] , $consult_time['duration_count'] + $get['duration']);
        $this->assertEquals( ConsultTime::$consult_times[$consult_time['id']]['delayed_num'] , $consult_time['delayed_num'] + 1);
        $this->assertEquals( ConsultTime::$consult_times[$consult_time['id']]['delayed_duration_total'] , $consult_time['delayed_duration_total'] + $get['duration']);
    }
}