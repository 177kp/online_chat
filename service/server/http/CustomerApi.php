<?php
namespace onlineChat\server\http;
use onlineChat\model\Session;
use onlineChat\model\Message;
use onlineChat\model\Publisher;

class CustomerApi{ 
    /**
     * 获取咨询客服的用户
     */
    static function getCustomerContacts($get){
        $users = [];
        //var_dump(Session::$sessions);
        foreach( Session::$tmpUserIndex as $access_token=>$v ){
            if( Session::$sessions[$access_token]['to_id'] == "" ){
                $users[] = [
                    'chat_type'=>2,
                    'to_id'=>Session::$sessions[$access_token]['uid'],
                    'name'=>'',
                    'head_img'=>''
                ];
            }
        }
        return httpApiMsg(200,'',$users);
    }
    /**
     * 客服接入
     */
    static function customerJoin($get){
        
        if( empty($get['uid']) ){
            return httpApiMsg(100,'uid参数不能为空！');
        }
        if( empty($get['to_id']) ){
            return httpApiMsg(100,'to_id参数不能为空！');
        }
        if( !isset($get['tmp']) ){
            $get['tmp'] = 1;
        }
        
        $sessions = Session::getByUid($get['to_id'],$get['tmp']);
        foreach( $sessions as $session ){
            if( $session['to_id'] != "" ){
                return httpApiMsg(100,'已接入客服了！');
            }
        }
        if( empty($sessions) ){
            return httpApiMsg(100,'用户不存在！');
        }
        $msg = Message::genCustomerJoin($get['uid'],$get['to_id']);
        Publisher::instance()->publish($msg);
        foreach( $sessions as $session ){
            $access_token = $session['access_token'];
            Session::writeFrameByAccessToken($access_token,$msg);
            Session::$sessions[$access_token]['to_id'] = $get['uid'];
            //var_dump(Session::$sessions[$access_token],$access_token);
        }
        if( !empty($session) ){
            //给客服广播用户已接入
            $msg = Message::genCustomerJoin($session['uid']);
            foreach( Session::$customerIndex as $access_token=>$v ){
                Session::writeFrameByUid(Session::$sessions[$access_token]['uid'],Session::USER_NORMAL,$msg);
            }
        }
        return httpApiMsg(200,'操作成功！');
        
    }
}