<?php
namespace onlineChat\server\websocket;
use onlineChat\model\Session;
use onlineChat\model\Room;
use onlineChat\model\Publisher;
use onlineChat\model\Message as MessageModel;
use onlineChat\model\ConsultTime;
use onlineChat\lib\protocol\Websocket;
use onlineChat\lib\websocketServer\ConnectionBase;
use think\facade\Log;

class OnMessage{
    /**
     * websocket消息回调
     * @param ConnectionBase $Connection
     * @param array $recvMsg
     */
    static function callback($Connection,$frame){
        //safe_dump($frame);
        if( empty($Connection) || !($Connection instanceof ConnectionBase) ){
            throw new \Exception('Connection不是ConnectionBase的继承类！');
        }
        $Websocket = Websocket::instance();
        if( $frame == "" ){
            return;
        }
        
        $recvMsg = json_decode($frame['payload-data'],true);
        if( $recvMsg == false ){
            return;
        }
        if( !isset($recvMsg['access_token']) ){
            $msg = MessageModel::genErrorMessage(300,'websocket消息，access_token参数不存在！请登录！');
            Session::writeFrameByFd($Connection->fd,$msg);
            return;
        }
        if( !isset(Session::$sessions[$recvMsg['access_token']]) ){
            $msg = MessageModel::genErrorMessage(300,'websocket消息，access_token不正确！请重新登录');
            Session::writeFrameByFd($Connection->fd,$msg);
            return;
        }
        if( !isset($recvMsg['to_id']) ){
            $msg = MessageModel::genErrorMessage(100,'websocket消息，to_id参数不存在！');
            Session::writeFrameByFd($Connection->fd,$msg);
            return;
        }
        if( !isset($recvMsg['chat_type']) ){
            $msg = MessageModel::genErrorMessage(100,'websocket消息，chat_type参数不存在！');
            Session::writeFrameByFd($Connection->fd,$msg);
            return;
        }
        if( !isset($recvMsg['msg']) ){
            $msg = MessageModel::genErrorMessage(100,'websocket消息，msg参数不存在！');
            Session::writeFrameByFd($Connection->fd,$msg);
            return;
        }
        if( !isset($recvMsg['msg_type']) ){  
            $msg = MessageModel::genErrorMessage(100,'websocket消息，msg_type参数不存在！');
            Session::writeFrameByFd($Connection->fd,$msg);
            return;
        }
        
        $session = Session::$sessions[$recvMsg['access_token']];
        $ctime = time();
        //Log::info( "recv,chat_type:" . $recvMsg['chat_type'] . ",from_uid:" . $session['uid'] . ',to_id:' . $recvMsg['to_id'] . ',msg_length:' . strlen($recvMsg['msg']) );
        //普通消息
        if( $recvMsg['chat_type'] == Session::CHAT_TYPE_CHAT ){

            self::chat($session,$Connection,$recvMsg);

        }elseif($recvMsg['chat_type'] == Session::CHAT_TYPE_ROOM ){
        //群聊消息
            self::room($session,$Connection,$recvMsg);

        }elseif( $recvMsg['chat_type'] == Session::CHAT_TYPE_CUSTOMER ){
        //咨询客服消息
            self::customer($session,$Connection,$recvMsg);
        }elseif( $recvMsg['chat_type'] == Session::CHAT_TYPE_CONSULTANT ){
            //var_export($session);
            self::Consult($session,$Connection,$recvMsg);
        }
    }
    static function chat($session,$Connection,$recvMsg){
        $msg = MessageModel::genMessage($session['uid'],Session::CHAT_TYPE_CHAT,$recvMsg['to_id'],$recvMsg['msg_type'],$recvMsg['msg']);
        Publisher::instance()->publish($msg);
        //加上发送消息人的头像和名称
        $msg['msg']['head_img'] = $session['head_img'];
        $msg['msg']['name'] = $session['name'];
        //推送通知给自己
        Session::writeFrameByUid($session['uid'],Session::USER_NORMAL,$msg);
        //推送通知给聊天的人
        Session::writeFrameByUid($recvMsg['to_id'],Session::USER_NORMAL,$msg);
    }
    static function room($session,$Connection,$recvMsg){
        //var_dump($recvMsg);
        $msg = MessageModel::genMessage($session['uid'],Session::CHAT_TYPE_ROOM,$recvMsg['to_id'],$recvMsg['msg_type'],$recvMsg['msg']);
        //加上发送消息人的头像和名称
        $msg['msg']['head_img'] = $session['head_img'];
        $msg['msg']['name'] = $session['name'];
        Publisher::instance()->publish($msg);
        if( isset(Room::$rooms[$recvMsg['to_id']]) ){
            //群消息广播
            foreach( Room::$rooms[$recvMsg['to_id']] as $uid ){
                Session::writeFrameByUid($uid,Session::USER_NORMAL,$msg);
            }
        }
    }
    static function Customer($session,$Connection,$recvMsg){
        $msg = MessageModel::genMessage($session['uid'],Session::CHAT_TYPE_CUSTOMER,$recvMsg['to_id'],$recvMsg['msg_type'],$recvMsg['msg']);
        //加上发送消息人的头像和名称
        $msg['msg']['head_img'] = $session['head_img'];
        if( $session['tmp'] == Session::USER_TMP ){
            $msg['msg']['name'] = '匿名用户' . $session['uid'];
        }else{
            $msg['msg']['name'] = $session['name'];
        }
        Publisher::instance()->publish($msg);
        //推送通知给自己
        Session::writeFrameByUid($session['uid'],$session['tmp'],$msg);
        //var_export($recvMsg);
        if( $recvMsg['to_id'] != '' ){
            if( $session['tmp'] == Session::USER_NORMAL ){
                $tmp = Session::USER_TMP;
            }else{
                $tmp = Session::USER_NORMAL;
            }
            //var_export($tmp);
            //推送通知给聊天的人
            Session::writeFrameByUid($recvMsg['to_id'],$tmp,$msg);
        }
    }
    static function Consult($session,$Connection,$recvMsg){
        if( $session['user_type'] == '0' ){
            $key = $session['uid'] . '-' . $recvMsg['to_id'];
        }elseif($session['user_type'] == '2' ){
            $key = $recvMsg['to_id'] . '-' . $session['uid'];
        }else{
            return;
        }
        if( !isset(ConsultTime::$index[$key]) ){
            $head_img = '';
            $name = '';
            if( $session['user_type'] == '0' ){
                $msg = '~亲，你还未未开启咨询哦！';
            }else{
                $msg = '~亲，用户还未未开启咨询哦！';
            }
            $msg = MessageModel::genConsultNotStartMessage($recvMsg['to_id'],$session['uid'],$msg);
            //Publisher::instance()->publish($msg); //该消息不需要发布
            //var_export(Session::$sessions);
            //var_export($msg) . PHP_EOL;
            //推送通知给当前客户端
            Session::writeFrameByFd($Connection->fd,$msg);
            return;
        }
        //咨询消息
        $msg = MessageModel::genMessage($session['uid'],3,$recvMsg['to_id'],$recvMsg['msg_type'],$recvMsg['msg']);
        //加上发送消息人的头像和名称
        $msg['msg']['head_img'] = $session['head_img'];
        $msg['msg']['name'] = $session['name'];
        Publisher::instance()->publish($msg);
        //echo $msg . PHP_EOL;
        //推送通知给自己
        Session::writeFrameByUid($session['uid'],$session['tmp'],$msg);
        //推送通知给聊天的人
        if( $recvMsg['to_id'] != '' ){
            //safe_dump($recvMsg);
            Session::writeFrameByUid($recvMsg['to_id'],Session::USER_NORMAL,$msg);
        }
    }
}