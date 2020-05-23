<?php
namespace onlineChat\server\http;
use onlineChat\model\Session;

class UserApi{
    /**
     * 获取websocket的access_token
     * @param array $get HTTP的get参数
     */
    static function get_access_token($get){

        if( empty($get['uid']) ){
            return httpApiMsg(100,'uid不能为空！');
        }
        if( !isset($get['name']) ){
            return httpApiMsg(100,'name参数不存在！');
        }
        if( !isset($get['head_img']) ){
            return httpApiMsg(100,'head_img参数不存在！');
        }
        if( !isset($get['tmp']) || $get['tmp'] != 1){
            if( empty($get['name']) ){
                return httpApiMsg(100,'name不能为空！');
            }
            if( empty($get['head_img']) ){
                return httpApiMsg(100,'head_img不能为空！');
            }
        }
        if( !isset($get['user_type']) ){
            return httpApiMsg(100,'user_type参数不存在！');
        }
        if( !in_array($get['user_type'],['0','1','2']) ){
            return httpApiMsg(100,'user_type参数不正确！');
        }
        if( isset($get['tmp']) && $get['tmp'] == '1' ){
            $tmp = 1; // 是临时用户
        }else{
            $tmp = 0; //不是临时用户
        }
        if( isset($get['to_id']) && $get['tmp'] == Session::USER_TMP ){
            $to_id = $get['to_id']; //客服id
        }else{
            $to_id = '';//客服id为空，则等待接入
        }
        //var_export($get);
        if( !isset($get['sessions']) ){
            return httpApiMsg(100,'sessions参数不存在！');
        }
        $get['sessions'] = json_decode($get['sessions'],true);
        if( empty($get['sessions']) ){
            $get['sessions'] = [];
        }
        foreach( $get['sessions'] as $session ){
            if( !isset($session['to_id']) ){
                httpApiMsg(100,'sessions[][to_id]参数不存在！');
            }
            if( !isset($session['chat_type']) ){
                httpApiMsg(100,'sessions[][chat_type]参数不存在！');
            }
            if( !isset($session['tmp']) ){
                httpApiMsg(100,'sessions[][tmp]参数不存在！');
            }
        }
        
        $access_token = Session::genAccessToken();
        Session::add($access_token,$get['uid'],$get['user_type'],$get['name'],$get['head_img'],$get['sessions'],$tmp,$to_id);
        //Log::info('get access_token,uid:' . $get['uid'] . ',access_token:'.$access_token);
        return httpApiMsg(200,'登录成功！',[
            'access_token'=>$access_token
        ]);

    }
    /**
     * 获取用户信息
     * @param array $get HTTP的get参数
     */
    static function userinfo($get){
        if( empty($get['access_token']) ){
            return httpApiMsg(100,'access_token不能为空！');
        }
        if( !isset(Session::$sessions[$get['access_token']]) ){
            return httpApiMsg(100,'用户信息不存在！');
        }
        //Log::info('getUserInfo,access_token:' . $get['access_token']);
        return httpApiMsg(200,'获取登录信息成功！',[
            'uid'=>Session::$sessions[$get['access_token']]['uid'] 
        ]);
    }
}