<?php
namespace onlineChat\server\http;
use onlineChat\model\Room;

class ChatRoomApi{
    /**
     * 加入聊天室，修改Room的数据
     * @param array $get HTTP的get参数
     */
    static function join($get){
        if( empty($get['rid']) ){
            return httpApiMsg(100,'rid不能为空！');
        }
        if( empty($get['uid']) ){
            return httpApiMsg(100,'uid不能为空！');
        }
        Room::join($get['rid'],$get['uid']);
        //Log::info('join room,uid:' . $get['uid'] . ',rid:'.$get['rid']);
        return httpApiMsg(200,'');
    }
    /**
     * 退出聊天室，修改Room的数据
     * @param $get HTTP的get参数
     */
    static function signOut($get){
        if( empty($get['rid']) ){
            return httpApiMsg(100,'rid不能为空！');
        }
        if( empty($get['uid']) ){
            return httpApiMsg(100,'uid不能为空！');
        }
        Room::del($get['rid'],$get['uid']);
        //Log::info('del room,uid:' . $get['uid'] . ',rid:'.$get['rid']);
        return httpApiMsg(200,'');
    }
}