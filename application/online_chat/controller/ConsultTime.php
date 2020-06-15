<?php
namespace app\online_chat\controller;
use think\Db;
use think\facade\Request;
use think\Controller;
use app\online_chat\serverApi\WebsocketServerApi;

class ConsultTime extends Controller{
    public function getConsultTime(){
        if( empty($_GET['to_id']) ){
            returnMsg(100,'to_id参数不能为空！');
        }
        $user_type = session('chat_user.user_type');
        if( $user_type == 0 ){
            $uid = session('chat_user.uid');
            $to_id = $_GET['to_id'];
        }elseif( $user_type == 2 ){
            $uid = $_GET['to_id'];
            $to_id = session('chat_user.uid');
        }else{
            returnMsg(100,'用户类型不正确！');
        }
        //var_dump($uid,$to_id);exit; 
        $sql = 'select * from chat_consult_time
                    where uid = ? and to_id=? and soft_delete=0 and (free_duration>0 || duration_count>0) and status < 3
                        order by free_duration desc,duration_count asc, id asc limit 100';
        $consultTimes = db::query($sql,[$uid,$to_id]);

        $freeConsult = 0;
        $sql = 'select * from chat_consult_time
                    where uid = ? and to_id=? and soft_delete=0 and free_duration>0 
                        order by id desc limit 1';
        $tmpConsultTimes = db::query($sql,[$uid,$to_id]);
        if( !empty($tmpConsultTimes) ){
            if( $tmpConsultTimes[0]['ctime'] < time() - 2*24*3600 ){
                $freeConsult=1;
            }
        }else{
            $freeConsult = 1;
        }
        $showFreeButton = 0;
        if( empty($consultTimes) ){
            $consult_time = [];
        }else{
            $consult_time = $consultTimes[0];
        }
        foreach( $consultTimes as $consultTime ){
            if( $consult_time['status'] == '1' ){
                $consult_time = $consultTime;
                break;
            }
        }
        if( isset($consult_time['soft_delete']) ){
            unset($consult_time['soft_delete']);
        }
        returnMsg(200,'',[
            'consult_time'=>$consult_time,
            'freeConsult'=>$freeConsult
        ]);
    }
    /**
     * 添加免费咨询 
     */
    public function addFreeConsult(){
        if( empty($_POST['to_id']) ){
            returnMsg(100,'to_id参数不能为空！');
        }
        $consultServer = db::table('chat_user')->where('uid',$_POST['to_id'])->where('user_type',2)->where('soft_delete=0')->find();
        if( empty($consultServer) ){
            returnMsg(100,'to_id参数不正确！');
        }
        $free_time = 300;
        $uid = session('chat_user.uid');
        $sql = 'select * from chat_consult_time
                    where uid = ? and to_id=? and soft_delete=0 and free_duration>0 
                        order by id desc limit 1';
        $consultTimes = db::query($sql,[$uid,$_POST['to_id']]);
        if( !empty($consultTimes) ){
            if( $consultTimes[0]['ctime'] > time() - 2*24*3600 ){
                returnMsg(100,'您已在两天里面领取免费咨询了',[
                    'consult_time'=>$consultTimes[0]
                ]);
            }
        }
        $id  = db::table('chat_consult_time')->insertGetId([
            'uid'=>$uid,
            'to_id'=>$_POST['to_id'],
            'status'=>0,
            'duration_count'=>0,
            'duration'=>0,
            'free_duration_count'=>$free_time,
            'free_duration'=>$free_time,
            'total_duration'=>$free_time,
            'delayed_duration_total'=>0,
            'delayed_num'=>0,
            'ctime'=>time(),
            'soft_delete'=>0
        ]);
        $consult_time = db::table('chat_consult_time')->where('id',$id)->find();
        $chatSession = db::table('chat_session')->where('uid',$_POST['to_id'])->where('to_id',$uid)->where('chat_type=3')->find();
        if( empty($chatSession) ){
            db::table('chat_session')->insert([
                'uid'=>$_POST['to_id'],
                'to_id'=>$uid,
                'chat_type'=>3,
                'last_time'=>0,
                'soft_delete'=>0
            ]);
        }
        returnMsg(200,'',[
            'consult_time'=>$consult_time
        ]);
    }
    /**
     * 新增计时
     */
    public function addConsult(){
        if( empty($_POST['to_id']) ){
            returnMsg(100,'to_id参数不能为空！');
        }
        $consultServer = db::table('chat_user')->where('uid',$_POST['to_id'])->where('user_type',2)->where('soft_delete=0')->find();
        if( empty($consultServer) ){
            returnMsg(100,'to_id参数不正确！');
        }
        $duration = 1800;
        $uid = session('chat_user.uid');
        $id  = db::table('chat_consult_time')->insertGetId([
            'uid'=>$uid,
            'to_id'=>$_POST['to_id'],
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

        $consult_time = db::table('chat_consult_time')->where('id',$id)->find();
        $chatSession = db::table('chat_session')->where('uid',$_POST['to_id'])->where('to_id',$uid)->where('chat_type=3')->find();
        if( empty($chatSession) ){
            db::table('chat_session')->insert([
                'uid'=>$_POST['to_id'],
                'to_id'=>$uid,
                'chat_type'=>3,
                'last_time'=>0,
                'soft_delete'=>0
            ]);
        }
        returnMsg(200,'',[
            'consult_time'=>$consult_time
        ]);
    }
    /**
     * 延时
     */
    public function delayedDuration(){
        if( empty($_POST['consult_time_id']) ){
            returnMsg(100,'consult_time_id参数不能为空！');
        }
        $uid =  session('chat_user.uid');
        
        Db::startTrans();
        $consult_time = db::table('chat_consult_time')->where('id',$_POST['consult_time_id'])->where('uid',$uid)->lock(true)->find();
        
        if( empty($consult_time) ){
            returnMsg(200,'consult_time_id参数不正确！');
        }
        if( $consult_time['status'] == '4' ){
            returnMsg(100,'咨询计时已取消了的');
        }
        if( $consult_time['status'] == '3' ){
            $status = 2;
        }else{
            $status = $consult_time['status'];
        }
        if( empty($consult_time) ){
            returnMsg(100,'consult_time_id参数不正确！');
        }
        if( empty($_POST['delayed_duration']) ){
            returnMsg(100,'delayed_duration参数不能为空！');
        }
        if( $_POST['delayed_duration'] < 0 ){
            returnMsg(100,'delayed_duration参数不能小于0！');
        }
        $duration = (int)$_POST['delayed_duration'];
        db::table('chat_consult_time')->where('id',$_POST['consult_time_id'])->update([
            'duration_count'=>$consult_time['duration_count'] + $duration,
            'total_duration'=>$consult_time['total_duration'] + $duration,
            'delayed_duration_total'=>$consult_time['delayed_duration_total'] + $duration,
            'delayed_num'=>$consult_time['delayed_num'] + 1,
        ]);
        db::commit();
        WebsocketServerApi::delayedDuration($duration,$_POST['consult_time_id']);
        returnMsg(200,'延时成功！');
    }
    /**
     * 开启咨询
     */
    public function startConsult(){
        if( empty($_POST['consult_time_id']) ){
            returnMsg(100,'consult_time_id参数不能为空！');
        }
        $uid =  session('chat_user.uid');
        $consult_time = db::table('chat_consult_time')->where('id',$_POST['consult_time_id'])->where('uid',$uid)->find();
        if( empty($consult_time) ){
            returnMsg(200,'consult_time_id参数不正确！');
        }
        $consultServer = db::table('chat_user')->where('uid',$consult_time['to_id'])->where('user_type',2)->where('soft_delete=0')->find();
        if( empty($consultServer) ){
            returnMsg(100,'没有找到咨询师');
        }
        if( $consultServer['online'] == '0' ){
            returnMsg(100,'咨询师不在线！',[
                'consult_server_online'=>0
            ]);
        }
        if( $consult_time['status'] == '0' ){
            
        }elseif( $consult_time['status'] == '1' ){
            returnMsg(100,'已经开启咨询了的！');
        }elseif( $consult_time['status'] == '2' ){

        }elseif( $consult_time['status'] == '3' ){
            returnMsg(100,'咨询已完成了的！');
        }else{
            returnMsg(100,'状态不正确！');
        }
        $consult_time['status'] = '1';
        WebsocketServerApi::startConsult($consult_time);
        session('start_consult_time',time());
        returnMsg(200,'开启咨询成功！');

    }
    /**
     * 暂停咨询
     */
    public function suspendConsult(){
        if( empty($_POST['consult_time_id']) ){
            returnMsg(100,'consult_time_id参数不能为空！');
        }
        if( time() - session('start_consult_time') < 3 ){
            returnMsg(100,'你操作太频繁，请稍后操作！');
        }
        $uid =  session('chat_user.uid');
        $consult_time = db::table('chat_consult_time')->where('id',$_POST['consult_time_id'])->where('uid',$uid)->find();
        if( empty($consult_time) ){
            returnMsg(200,'consult_time_id参数不正确！');
        }
        if( $consult_time['status'] == '0' ){
            returnMsg(100,'还未开始咨询！');
        }elseif( $consult_time['status'] == '1' ){

        }elseif( $consult_time['status'] == '2' ){
            returnMsg(100,'已是暂停状态！');
        }elseif( $consult_time['status'] == '3' ){
            returnMsg(100,'咨询已完成了的！');
        }else{
            returnMsg(100,'状态不正确！');
        }
        db::table('chat_consult_time')->where('id',$_POST['consult_time_id'])->update([
            'status'=>2
        ]);
        WebsocketServerApi::suspendConsult($_POST['consult_time_id']);
        returnMsg(200,'暂停咨询成功！');
    }
}