<?php
namespace onlineChat\model;
use onlineChat\model\Session;
use onlineChat\model\Publisher;
/**
 * 咨询计时
 */
class ConsultTime{
    /**
     * @var array $consult_time 咨询技术记录
     */
    static $consult_times = [];
    /**
     * @var array $index 咨询技术索引
     */
    static $index = [];
    /**
     * 加入到咨询记录
     * @param array $consult_time
     */
    static function join($consult_time){
        self::$consult_times[$consult_time['id']] = [
            'id'=>$consult_time['id'],
            'uid'=>$consult_time['uid'],
            'to_id'=>$consult_time['to_id'],
            'duration_count'=>$consult_time['duration_count'],
            'free_duration_count'=>$consult_time['free_duration_count'],
            'total_duration'=>$consult_time['total_duration'],
            'delayed_duration_total'=>$consult_time['delayed_duration_total'],
            'delayed_num'=>$consult_time['delayed_num'],
            'status'=>$consult_time['status']
        ];
        $key = $consult_time['uid'] . '-' . $consult_time['to_id'];
        self::$index[ $key ]= $consult_time['id'];
    }
    /**
     * 删除咨询记录
     * @param int $consult_time_id 咨询记录id
     */
    static function del($consult_time_id){
        if( isset(self::$consult_times[$consult_time_id]) ){
            $key = self::$consult_times[$consult_time_id]['uid'] . '-' . self::$consult_times[$consult_time_id]['to_id'];
            unset(self::$consult_times[$consult_time_id]);
            unset(self::$index[$key]);
        }
    }
}