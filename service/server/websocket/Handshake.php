<?php
namespace onlineChat\server\websocket;
use onlineChat\model\Session;
use onlineChat\model\Message;
use onlineChat\model\Publisher;
use onlineChat\lib\protocol\Websocket;

class Handshake{
    /**
     * websocket握手回调
     * @param ConnectionBase $Connection
     * @param mixed $request
     */
    static function callback($Connection,$request){
        $query = parse_url($request['uri'],PHP_URL_QUERY );
        parse_str($query,$get);
        //var_export($get);
        $Websocket = Websocket::instance();
        if( !isset($get['access_token']) ){
            $msg = Message::genErrorMessage(300,'websocket握手，access_token参数不存在！请登录！');
            $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
            $outBuffer = Websocket::instance()->genFrame([
                'payload-data'=>$msg
            ]);
            $Connection->write($outBuffer);
            return;
        }
        
        if( !isset(Session::$tmpSessions[$get['access_token']]) ){
            $msg = Message::genErrorMessage(300,'websocket握手，access_token不正确！请重新登录');
            $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
            $outBuffer = Websocket::instance()->genFrame([
                'payload-data'=>$msg
            ]);
            $Connection->write($outBuffer);
            return;
        }

        Session::setConnection($get['access_token'],$Connection);

        //广播online消息
        $session = Session::$sessions[$get['access_token']];
        $msg = Message::genOnlineMessage($session['uid'],$session['tmp'],0);
        Publisher::instance()->publish($msg);
        foreach( $session['sessions'] as $tmpSession ){
            Session::writeFrameByUid($tmpSession['to_id'],$tmpSession['tmp'],$msg);
        }
        //var_export($get);
        //咨询客服，没有接入客服发送欢迎信息
        if( isset($get['tmp']) && $get['tmp'] == '1' && isset($get['welcome']) && $get['welcome'] == '1' ){ 
            $session = Session::$sessions[$get['access_token']];
            $msg = Message::genCustomerNewUser($session['uid'],'欢迎咨询客服！正在为你接入客服！');
            Publisher::instance()->publish($msg);
            Session::writeFrameByFd($Connection->fd,$msg);
            $msg = Message::genWaitCustomerJoin($session['uid'],2);
            foreach( Session::$customerIndex as $access_token=>$v ){
                Session::writeFrameByUid(Session::$sessions[$access_token]['uid'],Session::$sessions[$access_token]['tmp'],$msg);
            }
        }

    }
}