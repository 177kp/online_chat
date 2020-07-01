<?php
namespace onlineChat\model;
use onlineChat\model\ConsultTime;
use onlineChat\model\Database;
/**
 * 消息
 */
class Message{

    /**
     * @const MSG_TYPE_TXT 0 文本
     */
    const MSG_TYPE_TXT = 0;
    /**
     * @const MSG_TYPE_IMG 1 图片
     */
    const MSG_TYPE_IMG = 1;
    /**
     * @const MSG_TYPE_SOUND 2 声音
     */
    const MSG_TYPE_SOUND = 2;
    /**
     * @const MSG_TYPE_VIDEO 3 视频
     */
    const MSG_TYPE_VIDEO = 3;
    /**
     * @const MSG_TYPE_RICH_TXT 4 富文本
     */
    const MSG_TYPE_RICH_TXT = 4;
    /**
     * @const MSG_TYPE_FILE 5 文件
     */
    const MSG_TYPE_FILE = 5;
    /**
     * 生成消息
     * @param int $uid 来源uid
     * @param int $chat_type 聊天类型，0-普通聊天，1-聊天室，2-客服，3-咨询
     * @param int $to_id
     * @param int $msg_type 0-文本,1-图片,10-客服接入
     * @param string $msg 消息内容
     * @param float $time 时间
     */
    static function genMessage($uid,$chat_type,$to_id,$msg_type,$msg,$ctime=null){
        if( $ctime == null ){
            $ctime = time();
        }
        return [
            'topic'=>'message',
            'msg'=>[
                'uid'=>$uid,
                'chat_type'=>$chat_type,
                'to_id'=>$to_id,
                'msg_type'=>$msg_type, 
                'msg'=>$msg,
                'ctime'=>$ctime
            ]
        ];
    }
    /**
     * 生成在线消息
     * @param int $uid 用户id
     * @param int $tmp 是否是临时用户
     * @param int $chat_type 聊天类型
     */
    static function genOnlineMessage($uid,$tmp,$chat_type){
        return [
            'topic'=>'online',
            'msg'=>[
                'uid'=>$uid,
                'tmp'=>$tmp,
                'chat_type'=>$chat_type,
                'ctime'=>time()
            ]
        ];
    }
    /**
     * 生成下线消息
     * @param int $uid 用户id
     * @param int $tmp 是否是临时用户
     * @param int $chat_type 聊天类型
     */
    static function genOfflineMessage($uid,$tmp,$chat_type){
        return [
            'topic'=>'offline',
            'msg'=>[
                'uid'=>$uid,
                'tmp'=>$tmp,
                'chat_type'=>$chat_type,
                'ctime'=>time()
            ]
        ];
    }
    /**
     * 生成心跳信息
     * @param array $users
     * [
     *    [
     *        uid=>用户id,
     *        tmp=>是否是临时用户      
     *    ],
     *    ...
     * ]
     */
    static function genHeartBeatMessage($users){
        return [
            'topic'=>'heartBeat',
            'msg'=>[
                'users'=>$users,
                'ctime'=>time()
            ]
        ];
    }
    /**
     * 生成咨询消息
     * @param int $consult_time_id 咨询时间id
     */
    static function genConsultTimeMessage($consult_time_id){
        return [
            'topic'=>'consult_time',
            'msg'=>[
                'consult_time'=>ConsultTime::$consult_times[$consult_time_id],
                'ctime'=>time()
            ]
        ];
    }
    /**
     * 生成咨询未开始消息
     * @param int $uid 用户id
     * @param int $to_id 发送给谁的id
     * @param string $msg 消息
     */
    static function genConsultNotStartMessage($uid,$to_id,$msg){
        return [
            'topic'=>'consult_not_start',
            'msg'=>[
                'uid'=>$uid,
                'chat_type'=>3,
                'to_id'=>$to_id,
                'msg'=>$msg,
                'msg_type'=>0,
                'ctime'=>time()
            ]
        ];
    }
    /**
     * 生成系统消息
     * @param int $uid 用户id
     * @param int $to_id 发送给谁的id
     * @param stirng $msg 消息
     */
    static function genSystemMessage($uid,$to_id='',$msg){
        return [
            'topic'=>'system',
            'msg'=>[
                'uid'=>$uid,
                'to_id'=>$to_id,
                'msg'=>$msg,
                'ctime'=>time()
            ]
        ];
    }
    /**
     * 生产客户的新用户消息
     * @param int $uid 用户id
     * @param string $msg 消息
     */
    static function genCustomerNewUser($uid,$msg){
        return [
            'topic'=>'cusomter_new_user',
            'msg'=>[
                'uid'=>'',
                'msg'=>$msg,
                'chat_type'=>2,
                'to_id'=>$uid,
                'msg_type'=>0,
                'ctime'=>time()
            ]
        ];
    }
    /**
     * 生成客服加入的广播消息
     * @param int $uid 用户id
     * @param string $chat_type 聊天类型
     */
    static function genCustomerJoin($uid,$to_id=''){
        return [
            'topic'=>'customer_join',
            'msg'=>[
                'uid'=>$uid,
                'chat_type'=>2,
                'msg'=>'客服接入',
                'to_id'=>$to_id,
                'msg_type'=>0,
                'ctime'=>time()
            ]
        ];
    }
    /**
     * 生成等待客服加入的广播消息
     * @param int $uid 用户id
     * @param string $name 用户名称
     * @param string $chat_type 聊天类型
     */
    static function genWaitCustomerJoin($uid){
        return [
            'topic'=>'wait_customer_join',
            'msg'=>[
                'uid'=>$uid,
                'chat_type'=>2,
                'msg'=>'等待客服加入',
                'to_id'=>'',
                'msg_type'=>0,
                'time'=>time()
            ]
        ];
    }
    /**
     * 生成server消息
     * @param array $msg
     */
    static function genServerInfo($msg){
        return [
            'topic'=>'serverInfo',
            'msg'=>$msg
        ];
    }
    /**
     * 生成error消息
     * @param int $code 错误码
     * @param string $msg 内容
     */
    static function genErrorMessage($code,$msg){
        return [
            'topic'=>'error',
            'code'=>$code,
            'error'=>$msg
        ];
    }
}