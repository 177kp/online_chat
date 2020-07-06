<?php
namespace app\online_chat\serverApi;
use app\online_chat\serverApi\WebsocketServerApi;
use think\Db;
class ModelApi{
    /**
     * @param int $uid 用户id
     * @param int $to_id 和谁聊天id
     * @param int $duration 时长
     * @return int 咨询计时id
     */
    static function addConsult($uid,$to_id,$duration){
        $id  = db::table('chat_consult_time')->insertGetId([
            'uid'=>$uid,
            'to_id'=>$to_id,
            'status'=>0,
            'duration_count'=>$duration,
            'duration'=>$duration,
            'free_duration_count'=>0,
            'free_duration'=>0,
            'total_duration'=>$duration,
            'delayed_duration_total'=>0,
            'delayed_num'=>0,
            'ctime'=>time(),
            'soft_delete'=>0
        ]);
        $chatSession = db::table('chat_session')->where('uid',$to_id)->where('to_id',$uid)->where('chat_type=3')->find();
        if( empty($chatSession) ){
            db::table('chat_session')->insert([
                'uid'=>$to_id,
                'to_id'=>$uid,
                'chat_type'=>3,
                'last_time'=>0,
                'soft_delete'=>0
            ]);
        }
        return $id;
    }
    /**
     * @param $uid 用户id
     * @param $consult_time_id 咨询计时id
     * @param $delayed_duration 延时时长
     * @throw ChatException
     * @return boolean
     */
    static function delayedDuration($uid,$consult_time_id,$delayed_duration){
        $consult_time = db::table('chat_consult_time')->where('id',$consult_time_id)->where('uid',$uid)->lock(true)->find();
        if( empty($consult_time) ){
            throw new ChatException('consult_time_id参数不正确！');
        }
        if( $consult_time['status'] == '4' ){
            throw new ChatException('咨询计时已取消了的');
        }
        if( $consult_time['status'] == '3' ){
            $status = 2;
        }else{
            $status = $consult_time['status'];
        }
        if( empty($consult_time) ){
            throw new ChatException('consult_time_id参数不正确！');
        }
        if( empty($delayed_duration) ){
            throw new ChatException('delayed_duration参数不能为空！');
        }
        if( $delayed_duration < 0 ){
            throw new ChatException('delayed_duration参数不能小于0！');
        }
        $duration = (int)$delayed_duration;
        db::table('chat_consult_time')->where('id',$consult_time_id)->update([
            'duration_count'=>$consult_time['duration_count'] + $duration,
            'total_duration'=>$consult_time['total_duration'] + $duration,
            'delayed_duration_total'=>$consult_time['delayed_duration_total'] + $duration,
            'delayed_num'=>$consult_time['delayed_num'] + 1,
        ]);
        WebsocketServerApi::delayedDuration($consult_time_id,$duration);
        return true;
    }
}