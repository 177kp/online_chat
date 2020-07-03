<?php
namespace app\online_chat\serverApi;

class WebsocketServerApi{

    static function get_access_token($uid,$user_type,$name,$head_img,$sessions,$tmp=0,$to_id=''){
        $params = [
            'action'=>'user/get_access_token',
            'uid'=>$uid,
            'user_type'=>$user_type,
            'name'=>$name,
            'head_img'=>$head_img,
            'sessions'=>json_encode($sessions),
            'tmp'=>$tmp
        ];
        if( !empty($to_id) ){
            $params['to_id'] = $to_id;
        }
        //var_export($params);exit;
        $res = self::httpGet($params);
        return $res['data']['access_token'];
    }
    static function getCustomerContacts(){
        $params = [
            'action'=>'customer/getCustomerContacts'
        ];
        $res = self::httpGet($params);
        return $res['data'];
    }
    static function roomJoin($uid,$rid){
        $params = [
            'action'=>'chatRoom/join',
            'uid'=>$uid,
            'rid'=>$rid
        ];
        $res = self::httpGet($params);
        return true;
    }
    static function customerJoin($uid,$to_id){
        $params = [
            'action'=>'customer/customerJoin',
            'uid'=>$uid,
            'to_id'=>$to_id
        ];
        $res = self::httpGet($params,false);
        return $res;
    }
    static function roomExit($uid,$to_id){
        $params = [
            'action'=>'chatRoom/exit',
            'uid'=>$uid,
            'rid'=>$to_id
        ];
        $res = self::httpGet($params);
        return true;
    }
    static function delayedDuration($consult_time_id,$duration){
        $params = [
            'action'=>'consultTime/delayed_duration',
            'duration'=>$duration,
            'consult_time_id'=>$consult_time_id
        ];
        $res = self::httpGet($params);
        return true;
    }
    static function startConsult($consult_time){
        $params = [
            'action'=>'consultTime/start_consult',
            'consult_time'=>$consult_time
        ];
        $res = self::httpGet($params);
        return true;
    }
    static function suspendConsult($consult_time_id){
        $params = [
            'action'=>'consultTime/suspend_consult',
            'consult_time_id'=>$consult_time_id
        ];
        $res = self::httpGet($params);
        return true;
    }

    static function httpGet($params,$throw=true){
        $res = file_get_contents( 'http://' . config('chat.server.http_host') . ':' .config('chat.server.http_port'). '?' . http_build_query($params) );
        //echo $res . PHP_EOL;
        $resArr = json_decode($res,true);
        if( $throw == true ){
            if( !isset($resArr['code']) || $resArr['code'] != '200' ){
                throw new \Exception($res);
            }
        }
        return $resArr;
    }
}