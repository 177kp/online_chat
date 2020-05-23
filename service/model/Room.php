<?php
namespace onlineChat\model;
/**
 * 聊天室房间
 */
class Room{
    /**
     * @var 聊天室数组
     * [
     *    rid=>[uid1,uid2,uid3]
     * ]
     */
    static $rooms;
    /**
     * 设置数据
     * @param int $rid 房间ID
     * @param array $uids 用户ID数组
     */
    static function set($rid,$uids){
        self::$rooms[$rid] = $uids;
    }
    /**
     * 加入群
     * @param int $rid 房间ID
     * @param int $uid 用户ID
     */
    static function join($rid,$uid){
        if( !isset(self::$rooms[$rid]) ){
            self::$rooms[$rid] = [$uid];
        }else{
            if( !in_array($uid,self::$rooms[$rid]) ){
                self::$rooms[$rid][] = $uid;
            }
        }
    }
    /**
     * 退出群
     * @param int $rid 房间ID
     * @param int $uid 用户ID
     */
    static function del($rid,$uid){
        if( isset(self::$rooms[$rid]) && in_array($uid,self::$rooms[$rid]) ){
            $index = array_search($uid,self::$rooms[$rid]);
            unset(self::$rooms[$rid][$index]);
        }
    }
    /**
     * 初始化聊天室数据
     */
    static function initData(){
        $pdo = Database::instance()->getDbConn();
        $sql = 'select * from chat_room where soft_delete=0';
        $rooms = $pdo->query($sql)->fetchAll();
        foreach( $rooms as $room ){
            $sql = 'select * from chat_session where soft_delete=0 and chat_type=1 and to_id='  .$room['rid'];
            $chat_sessions = $pdo->query($sql)->fetchAll();
            $uids = array_column($chat_sessions,'uid');
            Room::set( $room['rid'],$uids );
        }
    }
}