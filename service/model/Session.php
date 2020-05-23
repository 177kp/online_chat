<?php
namespace onlineChat\model;
use onlineChat\model\Message;
use onlineChat\model\Publisher;
use onlineChat\lib\websocketServer\ConnectionBase;
use onlineChat\lib\protocol\Websocket;
/**
 * session
 */
class Session{
    /**
     * @var $sessions 会话数组
     * [
     *     access_token=>[
     *         uid=>用户ID,
     *         login_time=>登录时间,
     *         connection=>连接（Connection）,
     *         session_uids=>[]
     *         fd=>fd
     *     ]
     * ]
     */
    static $sessions = [];
    /**
     * @var $tmpSessions 还未连接的session，通过登陆api加入的数据，websocket连上后，清除session，加入到self::$sessions
     */
    static $tmpSessions = [];
    /**
     * session数组的fd索引
     */
    static $fdIndex = [];
    /**
     * session数组的uid索引
     */
    static $uidIndex = [];
    /**
     * 客服的索引
     */
    static $customerIndex = [];
    /**
     * 咨询师索引
     */
    static $consultIndex = [];
    /**
     * 临时用户索引
     */
    static $tmpUserIndex = [];
    
    /**
     * @const USER_NORMAL 0 正常用户
     */
    const USER_NORMAL = 0;
    /**
     * @const USER_TMP 1 临时用户
     */
    const USER_TMP = 1;
    /**
     * @const USER_TYPE_CHAT 0 用户类型-普通聊天用户
     */
    const USER_TYPE_CHAT = 0;
    /**
     * @const USER_TYPE_CUSTOMER 1 用户类型-客服
     */
    const USER_TYPE_CUSTOMER = 1;
    /**
     * @const USER_TYPE_CONSULTANT 2 用户类型-咨询师
     */
    const USER_TYPE_CONSULTANT = 2;
    /**
     * @const CHAT_TYPE_CHAT 0 聊天类型-普通聊天
     */
    const CHAT_TYPE_CHAT = 0;
    /**
     * @const CHAT_TYPE_ROOM 1 聊天类型-群聊
     */
    const CHAT_TYPE_ROOM = 1;
    /**
     * @const CHAT_TYPE_CUSTOMER 2 聊天类型-和客服聊天
     */
    const CHAT_TYPE_CUSTOMER = 2;
    /**
     * @const CHAT_TYPE_CONSULTANT 3 聊天类型-和咨询师聊天
     */
    const CHAT_TYPE_CONSULTANT = 3; 
    
    /**
     * 添加session
     * @param string $access_token 
     * @param int $uid 用户id
     * @param int $user_type 0-聊天，1-客服，2-咨询师
     * @param string $name 用户名称
     * @param string $head_img 头像
     * @param mixed $sessions 已有的session
     * @param int $tmp 0-不是临时用户，1-临时用户
     * @param int $to_id 客服id
     */
    static function add($access_token,$uid,$user_type,$name,$head_img,$sessions,$tmp,$to_id){

        self::$tmpSessions[$access_token] = [
            'uid'=>$uid,
            'user_type'=>$user_type,
			'name'=>$name,
            'head_img'=>$head_img,
            'sessions'=>$sessions,
            'login_time'=>time(),
            'connection'=>null,
            'fd'=>null,
            'tmp'=>$tmp,
            'to_id'=>$to_id,
            'access_token'=>$access_token,
            'time'=>time()
        ];
        
    }
    /**
     * 给session设置connection
     * @param string $access_token
     * @param ConnectionBase $Connection
     */
    static function setConnection( $access_token , $Connection ){
        if( !isset(self::$tmpSessions[$access_token]) ){
            return false;
        }
        $uid = self::$tmpSessions[$access_token]['uid'];
        $tmp = self::$tmpSessions[$access_token]['tmp'];
        $user_type = self::$tmpSessions[$access_token]['user_type'];
        self::$sessions[$access_token] = self::$tmpSessions[$access_token];
        unset( self::$tmpSessions[$access_token] );
        $key = $tmp . '-' . $uid;
        if( isset(self::$uidIndex[$key]) ){
            self::$uidIndex[$key][] = $access_token;
        }else{
            self::$uidIndex[$key] = [$access_token];
        }
        if( $user_type == self::USER_TYPE_CUSTOMER ){
            self::$customerIndex[$access_token] = 0;
        }elseif( $user_type == self::USER_TYPE_CONSULTANT ){
            self::$consultIndex[$access_token] = 0;
        }
        if( $tmp == self::USER_TMP ){
            self::$tmpUserIndex[$access_token] = 0;
        }

        if(  !($Connection instanceof ConnectionBase) ){
            throw new \Exception('Connection不是ConnectionBase的继承类！');
        }
        if( !isset(self::$sessions[$access_token]) ){
            return;
        }
        self::$sessions[$access_token]['connection'] = $Connection;
        self::$sessions[$access_token]['fd'] = $Connection->fd;
        //safe_dump( $Connection->fd );
        self::$fdIndex[$Connection->fd] = $access_token;
    }
    /**
     * 通过fd删除session
     * @param mixed $var 
     */
    static function del($var,$type="fd"){
        //var_dump( self::$fdIndex[$fd] );
        if( $type == 'fd' ){ 
            if( !isset(self::$fdIndex[$var]) ){
                return;
            }
            $access_token = self::$fdIndex[$var];
        }elseif( $type == 'access_token' ){
            if( !isset(self::$sessions[$var]) ){
                return;
            }
            $access_token = $var;
        }else{
            return;
        }
        $uid = self::$sessions[$access_token]['uid'];
        $tmp = self::$sessions[$access_token]['tmp'];
        $key = $tmp . '-' . $uid;
        foreach( self::$uidIndex[$key] as $k=>$sid ){
            if( $access_token == $sid ){
                unset(self::$uidIndex[$key][$k]);
                break;
            }
        }
        
        if( self::$sessions[$access_token]['connection'] != null ){
            $fd = self::$sessions[$access_token]['connection']->fd;
            unset( self::$fdIndex[$fd] );
        }
        unset( self::$consultIndex[$access_token]);
        unset( self::$customerIndex[$access_token]);
        unset( self::$tmpUserIndex[$access_token]);
        unset( self::$sessions[$access_token]);
        if( count(self::$uidIndex[$key]) == 0 ){
            unset(self::$uidIndex[$key]);
            //发送离线消息
            Publisher::instance()->publish([
                'topic'=>'offline',
                'msg'=>[
                    'uid'=>$uid,
                    'tmp'=>$tmp
                ]
            ]);
            //echo "send offline" . PHP_EOL;
        }
    }
    /**
     * 通过uid来获取session
     * @param int $uid 用户ID
     * @param int $tmp 0-不是临时用户，1-临时用户
     */
    static function getByUid($uid,$tmp = self::USER_NORMAL){
        $sessions = [];
        $key = $tmp . '-' . $uid;
        if( isset(self::$uidIndex[$key]) ){
            foreach( self::$uidIndex[$key] as $access_token){
                $sessions[] = self::$sessions[$access_token];
            }
        }
        return $sessions;
    }
    /**
     * 是否存在uid的用户
     * @param int $uid 用户ID
     * @param int $tmp 0-不是临时用户，1-临时用户
     */
    static function isset($uid,$tmp=self::USER_NORMAL){
        $key = $tmp . '-' . $uid;
        if( isset(self::$uidIndex[$key]) ){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 生成access_token
     */
    static function genAccessToken(){
        $access_token = microtime(true) . mt_rand(1000,9999) . uniqid();
        return md5($access_token);
    }
    /**
     * 给某个用户发送消息
     * @param int $uid 用户ID
     * @param int $tmp 0-不是临时用户，1-临时用户
     * @param string $msg 消息
     */
    static function writeFrameByUid($uid,$tmp,$msg){
        $key = $tmp . '-' . $uid;
        //var_export($key);
        if( !isset(self::$uidIndex[$key]) ){
            return;
        }

        $access_token = end(self::$uidIndex[$key]);
        $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
        $outBuffer = Websocket::instance()->genFrame([
            'payload-data'=>$msg
        ]);
        $handle        = ord($outBuffer);
        $frame        = [];
        $frame['fin']    = ($handle >> 7) & 0x1 ;
        $frame['rsv1']   = ($handle >> 6) & 0x1;
        $frame['rsv2']   = ($handle >> 5) & 0x1;
        $frame['rsv3']   = ($handle >> 4) & 0x1;
        //var_export($frame);
        //var_dump( self::$uidIndex[$key] );
        foreach( self::$uidIndex[$key] as $access_token){
            if( isset(self::$sessions[$access_token]['connection']) ){
                self::$sessions[$access_token]['connection']->write($outBuffer);
            }
        } 
    }
    /**
     * 通过socket的fd给客户端发消息
     * @param int $fd 
     * @param array $msg 消息
     */
    static function writeFrameByFd($fd,$msg){
        if( !isset(self::$fdIndex[$fd]) ){
            return;
        }
        $access_token = self::$fdIndex[$fd];
        $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
        $outBuffer = Websocket::instance()->genFrame([
            'payload-data'=>$msg
        ]);
        if( isset(self::$sessions[$access_token]['connection']) ){
            self::$sessions[$access_token]['connection']->write($outBuffer);
        }
    }
    /**
     * 通过access_token给客户端发消息
     * @param string $access_token 访问token
     * @param array $msg 消息
     */
    static function writeFrameByAccessToken($access_token,$msg){
        $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
        $outBuffer = Websocket::instance()->genFrame([
            'payload-data'=>$msg
        ]);
        if( isset(self::$sessions[$access_token]['connection']) ){
            self::$sessions[$access_token]['connection']->write($outBuffer);
        }
    }
}