<?php
namespace app\online_chat\controller;
use think\Db;
use think\Request;
use app\online_chat\serverApi\WebsocketServerApi;
class Session{
    /**
     * 获取聊天会话列表
     */
    public function index(){
        isLogin();
        $uid = getUid();
        if( empty($_GET['page']) || $_GET['page'] < 1 ){
            $page = 1;
        }else{
            $page = $_GET['page'];
        }
        if( empty($_GET['pageNum']) || $_GET['pageNum'] < 1 ){
            $pageNum = 20;
        }else{
            $pageNum = $_GET['pageNum'];
        }
        if( $pageNum > 100 ){
            returnMsg(100,'参数pageNum不能大于100！');
        }
        $start = ($page - 1) * $pageNum;
        if( isset($_GET['user_type']) ){
            $user_type = $_GET['user_type'];
        }else{
            $user_type = session('chat_user.user_type');
        }
        if( $user_type == '0' ){ //聊天
            $sql = 'select uid,chat_type,to_id,name,head_img,last_time,online from (select a.uid,a.chat_type,a.to_id,b.name,b.head_img,a.last_time,b.online from chat_session a 
                        left join chat_user b on a.to_id = b.uid 
                            where chat_type = 0 and a.uid=? 
                            union all
                    select a.uid,a.chat_type,a.to_id,b.name,b.head_img,a.last_time,1 as online from chat_session a 
                        left join chat_room b on a.to_id = b.rid 
                            where chat_type = 1 and a.uid =? ) t
                            order by last_time desc limit 0,20';
            $chats = db::query($sql,[$uid,$uid]);
        }elseif( $user_type == '1' ){ //咨询客服
            $sql = 'select uid,chat_type,to_id,name,head_img,last_time,online from (select a.uid,a.chat_type,a.to_id,b.name,b.head_img,a.last_time,b.online from chat_session a 
                        left join chat_user b on a.to_id = b.uid 
                            where chat_type = 2 and a.uid=?  ) t
                            order by last_time desc limit 0,20';
            $chats = db::query($sql,[$uid]);
        }elseif( $user_type == '2' ){
            $sql = 'select a.uid,a.chat_type,a.to_id,b.name,b.head_img,a.last_time,b.online from chat_session  a
                        left join chat_user b on a.to_id = b.uid 
                where chat_type = 3 and a.uid =?';
            $chats = db::query($sql,[$uid]);    
            //var_dump($chats);exit;
        }
        $sqls = [];
        foreach( $chats as $chat ){
            if( $chat['chat_type'] == '0' ){
                $sqls[] = '(select a.uid,b.name,b.head_img,a.chat_type,a.to_id,a.msg_type,a.msg,a.ctime from chat_message a left join chat_user b on a.uid = b.uid where chat_type=0 
                                and ((a.uid='.$chat['uid'].' and a.to_id='.$chat['to_id'].') or (a.uid='.$chat['to_id'].' and a.to_id=' .$chat['uid'].')) 
                                order by mid desc limit 1)';
            }elseif( $chat['chat_type'] == '1' ){
                $sqls[] = '(select a.uid,b.name,b.head_img,a.chat_type,a.to_id,a.msg_type,a.msg,a.ctime from chat_message a left join chat_user b on a.uid = b.uid where chat_type=1 
                                and a.to_id='.$chat['to_id'].'
                                order by mid desc limit 1)';
            }elseif( $chat['chat_type'] == '2' ){
                $sqls[] = '(select a.uid,b.name,b.head_img,a.chat_type,a.to_id,a.msg_type,a.msg,a.ctime from chat_message a left join chat_user b on a.uid = b.uid where chat_type=2 
                                and a.to_id='.$chat['to_id'].'
                                order by mid desc limit 1)';
            }
        }
        if( !empty($sqls) ){
            $sql = implode(' union ',$sqls);
            $messages = db::query($sql,[]);
        }else{
            $messages = [];
        }
        $tmpMessages = [];
        foreach( $messages as $message ){
            if( $message['chat_type'] == '0' ){
                if( $message['uid'] == $uid ){
                    $key = $message['chat_type'] . '-' . $message['uid'] . '-' . $message['to_id'];
                }else{
                    $key = $message['chat_type'] . '-' . $message['to_id'] . '-' . $message['uid'];
                }
                $tmpMessages[$key] = $message;

            }elseif( $message['chat_type'] == '1' ){
                $key = $message['chat_type'] . '-' . $message['to_id'];
                $tmpMessages[$key] = $message;
            }
        }
        foreach( $chats as $k=>$chat ){
            if( $chat['chat_type'] == '0' ){
                if( $chat['uid'] == $uid ){
                    $key = '0' . '-' . $uid . '-' . $chat['to_id'];
                }else{
                    $key = '0' . '-' . $lastMessage['to_id'] . '-' . $uid;
                }
            }else{
                $key = '1' . '-' . $chat['to_id'];
            }
            if( isset($tmpMessages[$key]) ){
                $chats[$k]['lastMessage'] = $tmpMessages[$key];
            }else{
                $chats[$k]['lastMessage'] = null;
            }
            $chats[$k]['messages'] = [];
            if( $chat['name'] == "" ){
                $chats[$k]['name'] = "";
            }
        }
        returnMsg(200,'获取所有聊天会话成功！',$chats);
    }
    public function getContacts(){
        isLogin();
        //exit;
        //var_dump(getUid());exit;
        $user_type = session('chat_user.user_type');
        if( $user_type == 0 ){
            $sql = 'select 1 as chat_type,rid as to_id,head_img,name from chat_room union all
                    select 0 as chat_type,uid as to_id,head_img,name from chat_user where uid>9999 and user_type = 0 and uid <>' . getUid();
            $contacts = db::query($sql);
        }elseif( $user_type == 1 ){
            $contacts = WebsocketServerApi::getCustomerContacts();
            //var_export($contacts);exit;
        }elseif( $user_type == 2 ){
            $sql = 'select 3 as chat_type,uid as to_id,head_img,name from chat_user where uid>9999 and user_type = 2 and uid <>' . getUid();
            $contacts = db::query($sql);
        }else{
            $contacts = [];
        }
        returnMsg(200,'获取所有联系人成功！',$contacts);
    }
    public function getConsults(){
        $sql = 'select 3 as chat_type,uid as to_id,head_img,name from chat_user where uid>9999 and user_type=2';
        $contacts = db::query($sql);
        returnMsg(200,'',$contacts);
    }
    /**
     * 加入聊天会话
     */
    public function joinSession(){
        isLogin();
        if( !isset($_GET['chat_type']) ){
            returnMsg(100,'chat_type参数不存在！');
        }
        if( !in_array($_GET['chat_type'],['0','1','2','3']) ){
            returnMsg(100,'chat_type参数不正确！');
        }
        if( empty($_GET['to_id']) ){
            returnMsg(100,'to_id参数不能为空！');
        }
        if( $_GET['chat_type'] == '0' || $_GET['chat_type'] == '3'){
            if( $_GET['to_id'] == getUid() ){
                returnMsg(100,'to_id不能是自己的uid！');
            }
            $user = db::table('chat_user')->where('uid',$_GET['to_id'])->find();
            if( empty($user) ){
                returnMsg(100,'to_id不正确！');
            }
        }elseif( $_GET['chat_type'] == '1' ){
            $room = db::table('chat_room')->where('rid',$_GET['to_id'])->find();
            if( empty($room) ){
                returnMsg(100,'to_id不正确！');
            }
            WebsocketServerApi::roomJoin(getUid(),$room['rid']);
        }elseif( $_GET['chat_type'] == '2' ){
            $user = db::table('chat_tmp_user')->where('uid',$_GET['to_id'])->find();
            if( empty($user) ){
                returnMsg(100,'to_id不正确！');
            }
            WebsocketServerApi::customerJoin(getUid(),$_GET['to_id']);
            //var_export($res);exit;
            $sql = 'insert ignore into chat_session(sid,uid,to_id,chat_type,last_time)
                        values(null,?,?,?,unix_timestamp());';
            db::query($sql,[$_GET['to_id'],getUid(),$_GET['chat_type']]);
        }
        $sql = 'insert ignore into chat_session(sid,uid,to_id,chat_type,last_time)
                        values(null,?,?,?,unix_timestamp());';
        db::query($sql,[getUid(),$_GET['to_id'],$_GET['chat_type']]);
       
        returnMsg(200,'加入session成功！');
    }
    /**
     * 删除聊天会话
     */
    public function delSession(){
        isLogin();
        $uid = getUid();
        if( empty($_GET['to_id']) ){
            returnMsg(100,'to_id不能为空！');
        }
        if( !isset($_GET['chat_type']) ){
            returnMsg(100,'chat_type参数不存在！');
        }
        if( !in_array($_GET['chat_type'],['0','1']) ){
            returnMsg(100,'chat_type不正确！');
        }
        db::table('chat_session')->where('uid',$uid)->where('to_id',$_GET['to_id'])->where('chat_type',[$_GET['chat_type']])->delete();
        if( $_GET['chat_type'] == '1' ){
            WebsocketServerApi::roomExit($uid,$_GET['to_id']);
        }
        returnMsg(200,'删除成功！');
    }
}