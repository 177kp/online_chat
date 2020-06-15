<?php
namespace onlineChat\server\http;
use onlineChat\model\Session;
use onlineChat\model\ConsultTime as ConsultTimeModel;
use onlineChat\model\Message;
use onlineChat\model\Publisher;

class ConsultTimeApi{
    /**
     * 开始咨询
     */
    static function start_consult($get){
        if( !isset($get['consult_time']) ){
            return httpApiMsg(100,'consult_time参数不存在！');
        }
        if( !isset($get['consult_time']['id']) ){
            return httpApiMsg(100,'consult_time[id]参数不存在！');
        }
        if( !isset($get['consult_time']['uid']) ){
            return httpApiMsg(100,'consult_time[uid]参数不存在！');
        }
        if( !isset($get['consult_time']['to_id']) ){
            return httpApiMsg(100,'consult_time[to_id]参数不存在！');
        }
        if( !isset($get['consult_time']['duration_count']) ){
            return httpApiMsg(100,'consult_time[duration_count]参数不存在！');
        }
        if( !isset($get['consult_time']['free_duration_count']) ){
            return httpApiMsg(100,'consult_time[free_duration_count]参数不存在！');
        }
        if( !isset($get['consult_time']['total_duration']) ){
            return httpApiMsg(100,'consult_time[total_duration]参数不存在！');
        }
        if( !isset($get['consult_time']['status']) ){
            return httpApiMsg(100,'consult_time[status]参数不存在！');
        }
        if( !isset($get['consult_time']['delayed_duration_total']) ){
            return httpApiMsg(100,'consult_time[delayed_duration_total]参数不存在！');
        }
        if( !isset($get['consult_time']['delayed_num']) ){
            return httpApiMsg(100,'consult_time[delayed_num]参数不存在！');
        }
        ConsultTimeModel::join($get['consult_time']);
        $msg = Message::genConsultTimeMessage($get['consult_time']['id']);
        Publisher::instance()->publish($msg);
        Session::writeFrameByUid($get['consult_time']['uid'],Session::USER_NORMAL,$msg);
        Session::writeFrameByUid($get['consult_time']['to_id'],Session::USER_NORMAL,$msg);
        return httpApiMsg(200,'开启计时成功！');
    }
    /**
     * 暂停咨询
     */
    static function suspend_consult($get){
        if( !isset($get['consult_time_id']) ){
            return httpApiMsg(100,'consult_time_id参数不存在！');
        }
        $id = $get['consult_time_id'];
        if( isset(ConsultTimeModel::$consult_times[$id]) ){

            ConsultTimeModel::$consult_times[$id]['status'] = 2;
            $msg = Message::genConsultTimeMessage($id);
            Publisher::instance()->publish($msg);
            $consult_time = ConsultTimeModel::$consult_times[$id];
            Session::writeFrameByUid($consult_time['uid'],Session::USER_NORMAL,$msg);
            Session::writeFrameByUid($consult_time['to_id'],Session::USER_NORMAL,$msg);
            ConsultTimeModel::del($id);
            
        }
        return httpApiMsg(200,'暂停成功！');
    }
    /**
     * 延时
     */
    static function delayed_duration($get){
        if( empty($get['consult_time_id']) ){
            return httpApiMsg(100,'consult_time_id参数不能为空！');
        }
        if( empty($get['duration']) ){
            return httpApiMsg(100,'duration参数不能为空！');
        }
        if( $get['duration'] < 0 ){
            return httpApiMsg(100,'duration参数不能小于0！');
        }
        $duration = (int)$get['duration'];
        $id = $get['consult_time_id'];
        if( isset(ConsultTimeModel::$consult_times[$id]) ){
            ConsultTimeModel::$consult_times[$id]['duration_count'] += $duration;
            ConsultTimeModel::$consult_times[$id]['delayed_duration_total'] += $duration;
            ConsultTimeModel::$consult_times[$id]['delayed_num'] += 1;
        }
        /*
        $msg =[
            'code'=>200,
            'topic'=>'consult_delayed_duration',
            'msg'=>[
                'id'=>$id,
                'duration'=>$duration,
                'ctime'=>time()
            ]
        ];
        */
        //safe_dump($msg);
        //Publisher::instance()->publish('consult_time',$msg);
        return httpApiMsg(200,'延时成功！');
    }
}