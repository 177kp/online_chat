<?php
use PHPUnit\Framework\TestCase;
use onlineChat\model\Database;
include_once __DIR__ . '/../../init.php';
class DatabaseStock extends Database{

    private function createTable(){
        $sql = "CREATE TABLE `chat_message` (
            `mid` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `uid` INTEGER,
            `chat_type` INTEGER,
            `to_id` INTEGER,
            `msg_type` INTEGER,
            `msg` text(255),
            `ctime` INTEGER,
            `soft_delete` INTEGER
          )";
        $this->pdo->exec($sql);
        $sql = "CREATE TABLE `chat_session` (
            `sid` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `uid` INTEGER,
            `chat_type` INTEGER,
            `to_id` INTEGER,
            `last_time` INTEGER,
            `soft_delete` INTEGER
          );";
        $this->pdo->exec($sql);
        $sql = "CREATE TABLE `chat_message_text` (
            `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `message_id` INTEGER,
            `content` text
          );";
        $this->pdo->exec($sql);
        $sql = "CREATE TABLE `chat_user` (
            `uid` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `name` text(255),
            `head_img` text(255),
            `online` INTEGER,
            `last_login_time` INTEGER,
            `app_uid` INTEGER,
            `user_type` INTEGER,
            `last_heartbeat_time` INTEGER,
            `soft_delete` INTEGER
          )";
        $this->pdo->exec($sql);
    }
    public function getDbConn($reset = false){
        if( empty($this->pdo) ){
            $this->pdo = new PDO('sqlite::memory:',null,null,[
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE =>\PDO::FETCH_ASSOC
            ]);
            $this->createTable();
        } 
        return $this->pdo;
    }
}
class DatabaseTest extends TestCase{
    public function test_insertMessage(){

        $Database = new DatabaseStock();
        $dbConn = $Database->getDbConn();

        $msg = uniqid();
        $Database->insertMessage([
            'chat_type'=>0,
            'uid'=>1,
            'to_id'=>2,
            'msg_type'=>1,
            'msg'=>$msg
        ]);
        $sql = 'select * from chat_message order by mid desc limit 1';
        $message = $dbConn->query($sql)->fetch();
        $this->assertEquals($message['msg'],$msg);

        $msg = str_repeat( uniqid(),500 );
        $Database->insertMessage([
            'chat_type'=>0,
            'uid'=>1,
            'to_id'=>2,
            'msg_type'=>1,
            'msg'=>$msg
        ]);
        $sql = 'select * from chat_message order by mid desc limit 1';
        $message = $dbConn->query($sql)->fetch();
        $this->assertEquals($message['msg'],'');
        $sql = 'select * from chat_message_text order by id desc limit 1';
        $message = $dbConn->query($sql)->fetch();
        $this->assertEquals($message['content'],$msg);

    }
    public function test_setUserOffline(){
        $Database = new DatabaseStock();
        $dbConn = $Database->getDbConn();
        $uid = mt_rand(1,10000);
        $sql = 'insert into chat_user(uid,name,head_img,online,last_login_time,app_uid,user_type,last_heartbeat_time,soft_delete)
                    values('.$uid.',"123","",1,0,0,0,0,0)';
        $dbConn->exec($sql);
        $Database->setUserOffline($uid);
        $sql = 'select * from chat_user where uid=' . $uid;
        $chat_user = $dbConn->query($sql)->fetch();
        $this->assertEquals($chat_user['online'],0);
    }
    public function test_updateHeartbeat(){
        $Database = new DatabaseStock();
        $dbConn = $Database->getDbConn();
        $uids = [];
        for( $i=0;$i<100;$i++ ){
            $uids[] = mt_rand(1,10000);
        }
        $uids = array_unique($uids);
        foreach( $uids as $uid ){
            $sql = 'insert into chat_user(uid,name,head_img,online,last_login_time,app_uid,user_type,last_heartbeat_time,soft_delete)
            values('.$uid.',"123'.$uid.'","",1,0,0,0,0,0)';
            $dbConn->exec($sql);
        }
       
        $Database->updateHeartbeat($uids);
        $sql = 'select * from chat_user';
        $chat_users = $dbConn->query($sql)->fetchAll();
        $time = time();
        $res = true;
        foreach( $chat_users as $chat_user ){
            if( $time - $chat_user['last_heartbeat_time'] > 2 ){
                $res = false;
                break;
            }
        }
        $this->assertTrue($res);
    }

}