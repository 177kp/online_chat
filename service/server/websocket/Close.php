<?php
namespace onlineChat\server\websocket;
use onlineChat\model\Session;
use onlineChat\model\Message;
use onlineChat\model\Publisher;
use onlineChat\lib\protocol\Websocket;

class Close{
    /**
     * 关闭连接回调
     * @param int $fd
     */
    static function callback($fd){
        if( !isset(Session::$fdIndex[$fd]) ){
            return;
        }
        $access_token = Session::$fdIndex[$fd];
        if( !isset(Session::$sessions[$access_token]) ){
            return;
        }
        $session = Session::$sessions[$access_token];
        $msg = Message::genOfflineMessage($session['uid'],$session['tmp'],0);
        Publisher::instance()->publish($msg);

        $sessions = Session::getByUid($session['uid'],$session['tmp']);

        //一个用户当前只有一个会话（客户端），才广播离线消息
        if( count($sessions) == 1 ){
            foreach( Session::$sessions[$access_token]['sessions'] as $tmpSession ){
                //var_export($tmpSession);
                Session::writeFrameByUid($tmpSession['to_id'],$tmpSession['tmp'],$msg);
            }
            //var_dump($session['tmp']);
            if( $session['tmp'] == Session::USER_TMP ){
                if( $session['to_id'] == '' ){
                    //给客服广播离线消息
                    foreach( Session::$customerIndex as $access_token=>$v ){
                        Session::writeFrameByAccessToken($access_token,$msg);
                    }
                }else{
                    //给聊天的客服发离线消息
                    Session::writeFrameByUid($session['to_id'],Session::USER_NORMAL,$msg);
                }
            }
        }
        Session::del($fd);
    }
}