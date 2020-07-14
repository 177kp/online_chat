<?php
namespace onlineChat\model;
use onlineChat\model\Message;
use onlineChat\lib\ExceptionHandle;
class Database{
    /**
     * @var $instance 当前类的实例
     */
    static $instance;
    /**
     * @var $pdo 数据库连接
     */
    protected $pdo;
    /**
     * @var $lastMsgId 最近的消息的id
     */
    protected $lastMsgId;
    /**
     * 获取当前类实例
     * @return self
     */
    static function instance(){
        if( self::$instance == null ){
            self::$instance = new self;
        }
        return self::$instance;
    }
    /**
     * 获取数据库连接
     * @param boolean $reset 是否重置数据库连接；false-否，true-是
     * @return PDO
     */
    public function getDbConn($reset = false){
        //链接数据库，或重新连接
        if( empty($this->pdo) || $reset ){
            $dbConfig = include __DIR__ . '/../../config/database.php';
            try{
                $this->pdo = new \PDO($dbConfig['type'] . ':dbname=' . $dbConfig['database'] . ';host=' . $dbConfig['hostname'], $dbConfig['username'], $dbConfig['password'],[
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE =>\PDO::FETCH_ASSOC
                ]);
                $this->pdo->query('set names utf8mb4');
            }catch( \Exception $e ){
                echo '数据库连接失败！' . PHP_EOL;
            }
        }
        return $this->pdo;
    }
    /**
     * 关闭数据库连接
     */
    public function closeDbConn(){
        $this->pdo = null;
    }
    /**
     * 插入消息
     * @param array $params
     */
    public function insertMessage($params){
        try{
            $this->_insertMessage($params);
        }catch( \Exception $e ){
            ExceptionHandle::chatRenderException($e);
            if( $e instanceof \PDOException && $e->getCode() == 'HY000' ){
                $this->getDbConn(true);
            }
            $this->_insertMessage($params);
        }
        
    }
    protected function _insertMessage($params){
        if( !in_array($params['chat_type'],['0','1','2','3']) ){
            echo "chat_type类型不正确！" . PHP_EOL;
            return;
        }

        static $chat_sessions;
        if( $chat_sessions == null ){
            $chat_sessions = [];
        }
        //消息唯一标识符
        $uuid = md5( implode('',$params) . microtime(true) . mt_rand(1000,9999) );
        $dbConn = $this->getDbConn();
        //插入普通消息
        $sql = 'insert into chat_message(mid,uid,tmp,chat_type,to_id,msg_type,msg,ctime,uuid,soft_delete)
        values(null,?,?,?,?,?,?,'.time().',?,0)';
        $sth = $dbConn->prepare($sql);
        if( $params['msg_type'] == Message::MSG_TYPE_FILE || $params['msg_type'] == Message::MSG_TYPE_VIDEO || $params['msg_type'] == Message::MSG_TYPE_SOUND ){
            $params['msg'] = json_encode($params['msg']);
        }
        if( mb_strlen($params['msg']) <= 255 ){
            $sth->execute([$params['uid'],$params['tmp'],$params['chat_type'],$params['to_id'],$params['msg_type'],$params['msg'],$uuid]);
        }else{
            $sth->execute([$params['uid'],$params['tmp'],$params['chat_type'],$params['to_id'],$params['msg_type'],'',$uuid]);
            $sql = 'insert into chat_message_text(id,message_id,content)
                        values(null,?,?)';
            $sth = $dbConn->prepare($sql);
            $mid = $dbConn->lastInsertId();
            $sth->execute([$mid,$params['msg']]);
        }

       

        $params['uid'] = (int)$params['uid'];
        $params['to_id'] = (int)$params['to_id'];
        $params['chat_type'] = (int)$params['chat_type'];
        
        if( $params['chat_type'] == '2' && $params['tmp'] != '0' ){ //客服咨询加临时会话
            
            if( $params['tmp'] == '1' ){
                $key = $params['uid'] . '-' . $params['chat_type'] . '-tmp' ;
            }elseif( $params['tmp'] == '2' ){
                $key = $params['to_id'] . '-' . $params['chat_type'] . '-tmp' ;
            }
            if( !isset($chat_sessions[$key]) ){
                if( $params['tmp'] == '1' ){
                    $sql = 'select sid from chat_tmp_session
                                where chat_type=' . $params['chat_type'] . ' and to_id=' . $params['uid'] . ' and soft_delete=0';
                }elseif( $params['tmp'] == '2' ){
                    $sql = 'select sid from chat_tmp_session
                                where chat_type=' . $params['chat_type'] . ' and to_id=' . $params['to_id'] . ' and soft_delete=0';
                }
                $chat_session = $dbConn->query($sql)->fetch();
                if( empty($chat_session) ){
                    return;
                }
                $chat_sessions[$key] = $chat_session['sid'];
            }
            $sql = 'update chat_tmp_session set last_time=' . time() . ',last_msg_uuid="'.$uuid.'" where sid=' . $chat_sessions[$key];
            $dbConn->exec($sql);
            return;
        }

        $key = $params['uid'] . '-' . $params['to_id'] . '-' . $params['chat_type'];

        //更新发送方信息
        if( !isset($chat_sessions[$key]) ){
            $sql = 'select sid from chat_session 
                        where uid=' . $params['uid'] . ' and chat_type=' . $params['chat_type'] . ' and to_id=' . $params['to_id'] . ' and soft_delete=0';
            $chat_session = $dbConn->query($sql)->fetch();
            if( empty($chat_session) ){
                $sql = 'insert into chat_session(sid,uid,chat_type,to_id,last_time,last_msg_uuid,soft_delete)
                    values(null,'.$params['uid'].','.$params['chat_type'].','.$params['to_id'].','.time().',"'.$uuid.'",0);';
                $dbConn->exec($sql);
                $chat_sessions[$key] = $dbConn->lastInsertId();
            }else{
                $chat_sessions[$key] = $chat_session['sid'];
            }
        }
        //更新群聊
        if( $params['chat_type'] == 1 ){
            $sql = 'update chat_room set last_uid='.$params['uid'].',last_time=' . time() . ',last_msg_uuid="'. $uuid . '" where rid=' .$params['to_id']; 
            $dbConn->exec($sql);
        }
        //群聊的不更新，影响性能
        if( $params['chat_type'] != 1 ){
            $sql = 'update chat_session set last_time=' . time() . ',last_msg_uuid="'.$uuid.'" where sid=' . $chat_sessions[$key];
            $dbConn->exec($sql);
        }
        //更新接收方会话；群聊的不更新，影响性能
        if( $params['chat_type'] != 1 ){
            $key = $params['to_id'] . '-' . $params['uid'] . '-' . $params['chat_type'];
            if( !isset($chat_sessions[$key]) ){
                $sql = 'select sid from chat_session
                            where uid=' . $params['to_id'] . ' and chat_type=' . $params['chat_type'] . ' and to_id=' . $params['uid'] . ' and soft_delete=0';
                $chat_session = $dbConn->query($sql)->fetch();
                if( empty($chat_session) ){
                    $sql = 'insert into chat_session(sid,uid,chat_type,to_id,last_time,last_msg_uuid,soft_delete)
                        values(null,'.$params['to_id'].','.$params['chat_type'].','.$params['uid'].','.time().',"'.$uuid.'",0);';
                    $dbConn->exec($sql);
                    $chat_sessions[$key] = $dbConn->lastInsertId();
                }else{
                    $chat_sessions[$key] = $chat_session['sid'];
                }
            }
            $sql = 'update chat_session set last_time=' . time() . ',last_msg_uuid="'.$uuid.'" where sid=' . $chat_sessions[$key];
            $dbConn->exec($sql);
        }
    }
    /**
     * 设置用户下线
     */
    public function setUserOffline($uid,$tmp){
        if( $tmp == 0 ){
            $sql = 'update chat_user set online=0 where uid=' . (int)$uid;
            $this->getDbConn()->exec($sql);
        }else{
            $sql = 'update chat_tmp_user set online=0 where uid=' . (int)$uid;
            $this->getDbConn()->exec($sql);
        }
    }
    /**
     * 更新用户心跳时间，在线状态
     */
    public function updateHeartbeat($users){
        if( empty($users) ){
            return;
        }
        $uids = [];
        $tmpUids = [];
        foreach( $users as $key=>$user ){
            if( $user['tmp'] == 0 ){
                $uids[] = (int)$user['uid'];
            }else{
                $tmpUids[] = (int)$user['uid'];
            }
        }
        if( !empty($uids) ){
            $sql = 'update chat_user set last_heartbeat_time='.time().',online=1 where uid in(' . implode(',',$uids) . ')';
            $this->getDbConn()->exec($sql);
        }else{
            $sql = 'update chat_tmp_user set last_heartbeat_time='.time().',online=1 where uid in(' . implode(',',$tmpUids) . ')';
            $this->getDbConn()->exec($sql);
        }
    }
    /**
     * 更新咨询时间
     */
    public function update_consult_time($consult_time){

        $sql = 'update chat_consult_time set status=?,duration_count=?,free_duration_count=?,total_duration=?,delayed_duration_total=?,delayed_num=?
                    where id=?';
        $sth = $this->getDbConn()->prepare($sql);
        //var_export($params);
        $sth->execute([
            $consult_time['status'],
            $consult_time['duration_count'],
            $consult_time['free_duration_count'],
            $consult_time['total_duration'],
            $consult_time['delayed_duration_total'],
            $consult_time['delayed_num'],
            $consult_time['id']
        ]);
    }
    /**
     * 暂停所有的咨询
     */
    public function suspend_all_consult(){
        $sql = 'update chat_consult_time set status=2 where status=1';
        $this->getDbConn()->query($sql);
    }
}