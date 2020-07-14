<?php
namespace app\online_chat\controller;
use think\Db;
use think\Request;
use app\online_chat\serverApi\WebsocketServerApi;
class Session{
    /**
     * 获取聊天会话列表
     * $_GET[page] 不必须；分页
     * $_GET[pagaeNum] 不必须；每页数量；最大500
     * $_GET[type] 不必须；是all代表所有会话，分页无效
     * $_GET[chat_types] 不必须；指定聊天类型；有type=all则无效
     * 会话列表有最大数量的限制
     */
    public function index(){
        isLogin();
        $maxSessionNum = 500;
        if( session('chat_user.tmp') == '1' ){
            returnMsg(100,'临时用户不能获取会话列表！');
        }
        //当前用户id
        $uid = getUid();

        //分页相关数据
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
        if( $pageNum > $maxSessionNum ){
            returnMsg(100,'参数pageNum不能大于'.$maxSessionNum.'！');
        }
        $start = ($page - 1) * $pageNum;
        
        /**
         * 查询会话列表；
         * $_GET[type]=all代表所有会话；最多500条；分页不起作用的
         * 没有$_GET[type]则看$_GET[chat_types]；
         * 没有$_GET[chat_types]则取当前用户类型的默认chat_type
         */
        if( isset($_GET['type']) && $_GET['type'] == 'all' ){
            if( session('chat_user.user_type') == '1' ){ //用户类型是客服，需要查询临时会话表
                $sql = '(select uid,chat_type,to_id,last_time,last_msg_uuid,0 as tmp from chat_session 
                            where soft_delete=0 and uid=' . $uid . ' limit '.$maxSessionNum.') union all 
                        (select uid,chat_type,to_id,last_time,last_msg_uuid,1 as tmp from chat_tmp_session 
                            where soft_delete=0 and uid=' . $uid . ' limit '.$maxSessionNum . ')';
            }else{
                $sql = 'select uid,chat_type,to_id,last_time,last_msg_uuid,0 as tmp from chat_session where soft_delete=0 and uid=' . $uid;
            }
            $sql .= ' order by last_time desc limit 0,' . $maxSessionNum;
            $start = 0;
            $pageNum = $maxSessionNum;
        }else{
            //chat_types
            if( isset($_GET['chat_types']) ){
                $chat_types = explode(',',$_GET['chat_types']);
                foreach( $chat_types as $k=>$chat_type ){
                    $chat_types[$k] = (int)$chat_type;
                    if( !in_array($chat_type,['0','1','2','3']) ){
                        returnMsg(100,'chat_type参数不正确！');
                    }
                }
            }else{
                $user_type = session('chat_user.user_type');
                if( $user_type == '0' ){ //普通用户，普通聊天、群聊、咨询
                    $chat_types = [0,1,3];
                }else if( $user_type == '1' ){ //客服，客服聊天
                    $chat_types = [2];
                }else if( $user_type == '2' ){ //咨询师，咨询聊天
                    $chat_types = [3];
                }else{
                    returnMsg(100,'user_type不正确！');
                }
            }
            $sqlParts = [];
            foreach( $chat_types as $chat_type ){
                $sqlParts[] = 'chat_type='.$chat_type;
            }
            if( in_array(2,$chat_types) ){ //有客服聊天，需要查询临时会话表
                $sql = '(select uid,chat_type,to_id,last_time,last_msg_uuid,0 as tmp from chat_session 
                            where soft_delete=0 and uid=' . $uid . ' and (' . implode(' or ',$sqlParts) . ') limit ' . $maxSessionNum . ') union all 
                        (select uid,chat_type,to_id,last_time,last_msg_uuid,1 as tmp from chat_tmp_session 
                            where soft_delete=0 and uid=' . $uid . ' limit ' . $maxSessionNum . ')';
            }else{
                $sql = 'select uid,chat_type,to_id,last_time,last_msg_uuid,0 as tmp from chat_session 
                            where soft_delete=0 and uid=' . $uid . ' and (' . implode(' or ',$sqlParts) . ')';
            }
            
            if( in_array(1,$chat_types) ){
                $sql .= ' order by last_time desc limit 0,' . $maxSessionNum;
            }else{
                $sql .= ' order by last_time desc limit ' . $start . ',' . $pageNum;
            }
        }
        
        //echo $sql;exit;
        //查询会话列表
        $sessions = db::query($sql);

        //会话列表里面有群聊；就更新$session里的最近时间，最近消息唯一标识符
        $rids = [];
        foreach( $sessions as $session ){
            if( $session['chat_type'] == '1' ){
                $rids[] = $session['to_id'];
            }
        }
        if( !empty($rids) ){
            $rooms = db::table('chat_room')->where('rid','in',$rids)->select();
            $arr = [];
            foreach( $rooms as $room ){
                $arr[$room['rid']] = $room; 
            }
            foreach( $sessions as $k=>$session ){
                if( $session['chat_type'] == '1' ){
                    $sessions[$k]['online'] = 1;
                    if( isset($arr[$session['to_id']]) ){
                        $sessions[$k]['head_img'] = $arr[$session['to_id']]['head_img'];
                        $sessions[$k]['name'] = $arr[$session['to_id']]['name'];
                        $sessions[$k]['last_uid'] = $arr[$session['to_id']]['last_uid'];
                        $sessions[$k]['last_time'] = $arr[$session['to_id']]['last_time'];
                        $sessions[$k]['last_msg_uuid'] = $arr[$session['to_id']]['last_msg_uuid'];
                    }else{
                        $sessions[$k]['head_img'] = '';
                        $sessions[$k]['name'] = '';
                    }
                }
            }
            //重新排序
            usort($sessions,function($a,$b){
                if( $a['last_time'] >= $b['last_time'] ){
                    return -1;
                }else{
                    return 1;
                }
            });
            //对有分页情况有用
            $sessions = array_slice($sessions,$start,$pageNum);
        }
        //var_export($sessions);exit;
        //用户id数组，包含了群聊的最近发送消息的用户id
        $uids = [];
        //临时用户id数组
        $tmpUids = [];
        //消息的唯一标识数组
        $uuids = [];

        //从会话列表里面取$uuids,$rids,$tmpUids,$mids;增加online,head_img,name,messages字段
        foreach( $sessions as $k=>$session ){
            $sessions[$k]['messages'] = [];
            if( $session['chat_type'] != 1 ){
                $sessions[$k]['online'] = 0;
                $sessions[$k]['head_img'] = '';
                $sessions[$k]['name'] = '';
            }
            if( $session['chat_type'] == '0' || $session['chat_type'] == '3' ){
                $uids[] = $session['to_id'];
            }else if( $session['chat_type'] == '1' ){
                if( isset($session['last_uid']) ){
                    $uids[] = $session['last_uid'];
                }
            }else if( $session['chat_type'] == '2' ){
                if( $session['tmp'] == '0' ){
                    $uids[] = $session['to_id'];
                }else{
                    $tmpUids[] = $session['to_id'];
                }
                
            }
            if( !empty($session['last_msg_uuid']) ){
                $uuids[] = $session['last_msg_uuid'];
            }
        }
        //var_dump($uids,$rids,$tmpUids,$mids);exit;
        //查询用户信息，并更新sessions里面的online、head_img、name字段
        if( !empty($uids) ){
            $users = db::table('chat_user')->field('uid,name,head_img,online')->where('uid','in',$uids)->select();
            $arr = [];
            foreach( $users as $user ){
                $arr[$user['uid']] = $user;
            }
            foreach( $sessions as $k=>$session ){
                if( $session['chat_type'] == '0' || $session['chat_type'] == '3' || ($session['chat_type'] == '2' && $session['tmp'] == '0') ){
                    if( isset($arr[$session['to_id']]) ){
                        $sessions[$k]['online'] = $arr[$session['to_id']]['online'];
                        $sessions[$k]['head_img'] = $arr[$session['to_id']]['head_img'];
                        $sessions[$k]['name'] = $arr[$session['to_id']]['name'];
                    }
                }
            }
            $userArr = $arr;
        }

        //查询临时用户信息，并更新sessions里面的online、head_img、name字段
        if( !empty($tmpUids) ){
            $users = db::table('chat_tmp_user')->field('uid,online')->where('uid','in',$tmpUids)->select();
            $arr = [];
            foreach( $users as $user ){
                $arr[$user['uid']] = $user;
            }
            foreach( $sessions as $k=>$session ){
                if( $session['chat_type'] == '2' && $session['tmp'] == '1' ){
                    if( isset($arr[$session['to_id']]) ){
                        $sessions[$k]['online'] = $arr[$session['to_id']]['online'];
                        $sessions[$k]['head_img'] = '';
                        $sessions[$k]['name'] = '匿名用户'.$session['to_id'];
                    }else{
                        $sessions[$k]['online'] = 0;
                        $sessions[$k]['head_img'] = '';
                        $sessions[$k]['name'] = '匿名用户'.$session['to_id'];
                    }
                }
            }
        }

        /**
         * 查询会话的最近聊天信息，并更新sessions里面的lastMessage字段；
         * 需要处理消息的字段为空的情况，也就是长文本消息；需要查询一次chat_message_text表；
         * 消息加入发送消息人的name,head_img,online字段；
         * 没有最近消息的lastMessage字段为null
         */
        
        if( !empty($uuids) ){
            $messages = db::table('chat_message')->field('mid,uid,chat_type,to_id,msg_type,msg,ctime,uuid')->where('uuid','in',$uuids)->select();
            //var_export($messages);exit;
            $message_ids = [];
            foreach( $messages as $message ){
                if( $message['msg'] === '' ){
                    $message_ids[] = $message['mid'];
                }
            }
            $arr = [];
            if( !empty($message_ids) ){
                $message_texts = db::table('chat_message_text')->where('message_id','in',$message_ids)->select();
                foreach( $message_texts as $message_text ){
                    $arr[$message_text['message_id']] = $message_text;
                }
            }
            foreach( $messages as $k=>$message ){
                if( $message['chat_type'] == '1' ){
                    if( isset($userArr[$message['uid']]) ){
                        $messages[$k]['name'] = $userArr[$message['uid']]['name'];
                        $messages[$k]['head_img'] = $userArr[$message['uid']]['head_img'];
                        $messages[$k]['online'] = $userArr[$message['uid']]['online'];
                    }else{
                        $messages[$k]['name'] = '';
                        $messages[$k]['head_img'] = '';
                        $messages[$k]['online'] = 0;
                    }
                }
            }
            $arrMessages = [];
            foreach( $messages as $k=>$message ){
                if( $message['msg'] === '' ){
                    if( isset($arr[$message['mid']]) ){
                        $messages[$k]['msg'] = $arr[$message['mid']]['content'];
                    }
                }
                $arrMessages[$message['uuid']] = $messages[$k];
            }
            //var_export($arrMessages);exit;
            foreach( $sessions as $k=>$session ){
                if( !empty($session['last_msg_uuid']) && isset($arrMessages[$session['last_msg_uuid']]) ){
                    $sessions[$k]['lastMessage'] = $arrMessages[$session['last_msg_uuid']];
                    if( $session['chat_type'] == 1 ){
                        continue;
                    }
                    if( $session['to_id'] == $arrMessages[$session['last_msg_uuid']]['uid'] ){
                        $sessions[$k]['lastMessage']['name'] = $session['name'];
                        $sessions[$k]['lastMessage']['head_img'] = $session['head_img'];
                        $sessions[$k]['lastMessage']['online'] = $session['online'];
                    }else{
                        $sessions[$k]['lastMessage']['name'] = session('chat_user.name');
                        $sessions[$k]['lastMessage']['head_img'] = session('chat_user.head_img');
                        $sessions[$k]['lastMessage']['online'] = 1;
                    }
                }else{
                    $sessions[$k]['lastMessage'] = null;
                }
            }
        }
        //去掉会话列表mid,last_msg_uuid,uuid字段
        foreach( $sessions as $k=>$session ){
            unset($sessions[$k]['mid']);
            unset($sessions[$k]['last_msg_uuid']);
            unset($sessions[$k]['lastMessage']['uuid']);
        }
        returnMsg(200,'获取所有聊天会话成功！',$sessions);
    }
    /**
     * 获取通讯录列表
     * 默认情况;也就是$_GET[type]为空
     * 用户类型为普通用户，查询普通用户列表和群聊
     * 用户类型为客服，查询带接入客服列表
     * 用户类型为咨询师，通讯录为空
     * $_GET[type] string 类型
     * 用户类型是普通用户，$_GET[type]=all，查询用户列表（包含客服、咨询师）和群聊
     * 用户类型是客服，$_GET[type]=all，查询用户列表（包含客服、咨询师）、群聊、带接入客服列表；
     * 用户类型是客服，$_GET[type]=common，查询用户列表（包含客服、咨询师）、群聊；
     * 用户类型是咨询师，$_GET[type]=all，查询用户列表（包含客服、咨询师）和群聊
     */
    public function getContacts(){
        isLogin();
        /**
         * @var string $strategy
         * 策略
         * default-默认，查询所有用户、群聊；
         * mail_list采用通讯录；通讯录需要单独维护
         */
        $strategy = 'default';
        $user_type = session('chat_user.user_type');
        $contacts = [];

        //当前用户是客服，查询带接入的用户
        do{
            if( $user_type != 1 ){
                break;
            }
            if( !empty($_GET['type']) && $_GET['type'] == 'common' ){
                break;
            }
            $arr = WebsocketServerApi::getCustomerContacts();
            $contacts = array_merge($contacts,$arr);
            if( empty($_GET['type']) ){
                returnMsg(200,'获取所有联系人成功！',$contacts);
            }
        }while(0);

        //策略是通讯录，计算$mail_uids,$mail_rids
        if( $strategy == 'mail_list' ){
            $mail_uids = [];
            $mail_rids = [];
            $chat_mail_list = db::table('chat_mail_list')->where('uid',session('chat_user.uid'))->where('soft_delete=0')->find();
            if( !empty($chat_mail_list) ){
                $mail_uids = explode(',',$chat_mail_list['uids']);
                foreach( $mail_uids as $k=>$mail_uid ){
                    $mail_uids[$k] = (int)$mail_uid;
                }
                $mail_rids = explode(',',$chat_mail_list['rids']);
                foreach( $mail_rids as $k=>$mail_rid ){
                    $mail_rids[$k] = (int)$mail_rid;
                }
            }
        }

        //查询群聊列表
        do{
            if( $user_type == 0 ){
                if( !empty($_GET['type']) && !in_array($_GET['type'],['all']) ){
                    break;
                }
            }elseif( $user_type == 1 ){
                if( empty($_GET['type']) || !in_array($_GET['type'],['all','common']) ){
                    break;
                }
            }elseif( $user_type == 2 ){
                if( empty($_GET['type']) || !in_array($_GET['type'],['all']) ){
                    break;
                }
            }
            $Query = db::table('chat_room')->field('1 as chat_type,rid as to_id,head_img,name')->where('soft_delete=0')->order('rid desc')->limit(500);
            if( $strategy == 'mail_list' ){
                if( empty($mail_rids) ){
                    break;
                }
                $arr = $Query->where('rid','in',$mail_rids)->select();
            }else{
                $arr = $Query->select();
            }
            $contacts = array_merge($contacts,$arr);
        }while(0);
        
        //查询用户列表
        do{
            if( $user_type == 0 ){
                if( !empty($_GET['type']) && !in_array($_GET['type'],['all']) ){
                    break;
                }
            }elseif( $user_type == 1 ){
                if( empty($_GET['type']) || !in_array($_GET['type'],['all','common']) ){
                    break;
                }
            }elseif( $user_type == 2 ){
                if( empty($_GET['type']) || !in_array($_GET['type'],['all']) ){
                    break;
                }
            }
            $Query = db::table('chat_user')->field('0 as chat_type,uid as to_id,head_img,name')->where('soft_delete=0')->order('uid desc')->limit(500);
            $Query->where('uid','<>',getUid());
            if( $strategy == 'mail_list' ){
                if( empty($mail_uids) ){
                    break;
                }
                $Query->where('uid','in',$mail_uids);
            }
            if( empty($_GET['type']) ){
                if( $user_type == 0 ){
                    $Query->where('user_type',0); //默认情况，有用户限制
                }
            }
            $arr = $Query->select();
            //var_dump($arr);
            $contacts = array_merge($contacts,$arr);
        }while(0);
        returnMsg(200,'获取所有联系人成功！',$contacts);

    }
    /**
     * 获取咨询师列表
     */
    public function getConsults(){
        $sql = 'select 3 as chat_type,uid as to_id,head_img,name from chat_user where uid>9999 and user_type=2 order by uid desc limit 500';
        $contacts = db::query($sql);
        returnMsg(200,'',$contacts);
    }
    /**
     * 加入聊天会话
     * $_GET[type] 聊天类型；0，1，2，3
     * $_GET[to_id] 和谁聊天 
     * $_GET[type]=0；加入普通聊天
     * $_GET[type]=1；加入群聊，$_GET[to_id]是聊天室id
     * $_GET[type]=2；当前用户类型必须是咨询师，接待咨询客服的；$_GET[to_id]是临时用户id
     * $_GET[type]=3；加入咨询聊天
     */
    public function joinSession(){
        isLogin();
        /**
         * @var string $strategy
         * 策略
         * default-默认，查询所有用户、群聊；
         * mail_list采用通讯录；通讯录需要单独维护
         */
        $strategy = 'default';
        $user_type = session('chat_user.user_type');
        if( !isset($_GET['chat_type']) ){
            returnMsg(100,'chat_type参数不存在！');
        }
        if( !in_array($_GET['chat_type'],['0','1','2','3']) ){
            returnMsg(100,'chat_type参数不正确！');
        }
        if( empty($_GET['to_id']) ){
            returnMsg(100,'to_id参数不能为空！');
        }
        if( $_GET['chat_type'] == '0' ){ //加入普通聊天
            if( $_GET['to_id'] == getUid() ){
                returnMsg(100,'to_id不能是自己的uid！');
            }
            if( $strategy == 'mail_list' ){
                $chat_mail_list = db::table('chat_mail_list')->where('uid',getUid())->where('soft_delete=0')->find();
                if( empty($chat_mail_list) ){
                    returnMsg(101,'to_id不正确！');
                }
                $mail_uids = explode(',',$chat_mail_list['uids']);
                if( !in_array($_GET['to_id'],$mail_uids) ){
                    returnMsg(101,'to_id不正确！');
                }
            }
            $user = db::table('chat_user')->where('uid',$_GET['to_id'])->find();
            if( empty($user) ){
                returnMsg(100,'to_id不正确！');
            }
            $online = $user['online'];
        }elseif( $_GET['chat_type'] == '1' ){ //加入群聊
            if( $strategy == 'mail_list' ){
                $chat_mail_list = db::table('chat_mail_list')->where('uid',getUid())->where('soft_delete=0')->find();
                if( empty($chat_mail_list) ){
                    returnMsg(101,'to_id不正确！');
                }
                $mail_rids= explode(',',$chat_mail_list['rids']);
                if( !in_array($_GET['to_id'],$mail_rids) ){
                    returnMsg(101,'to_id不正确！');
                }
            }
            $room = db::table('chat_room')->where('rid',$_GET['to_id'])->where('soft_delete=0')->find();
            if( empty($room) ){
                returnMsg(100,'to_id不正确！');
            }
            WebsocketServerApi::roomJoin(getUid(),$room['rid']);
            $online = 1;
        }elseif( $_GET['chat_type'] == '2' ){ //客服接入用户
            if( session('chat_user.user_type') != 1 ){
                returnMsg(100,'当前用户类型不正确！');
            }
            //兼容老版本没有tmp参数
            if( !isset($_GET['tmp']) ){
                $_GET['tmp'] = 1;
            }
            if( !in_array($_GET['tmp'],['0','1']) ){
                returnMsg(100,'tmp参数不正确！');
            }
            if( $_GET['tmp'] ==  '1' ){
                $user = db::table('chat_tmp_user')->where('uid',$_GET['to_id'])->find();
                if( empty($user) ){
                    returnMsg(100,'to_id不正确！');
                }
            }else{
                $user = db::table('chat_user')->where('uid',$_GET['to_id'])->where('soft_delete=0')->find();
                if( empty($user) ){
                    returnMsg(100,'to_id不正确！');
                }
            }
            $res = WebsocketServerApi::customerJoin(getUid(),$_GET['to_id'],$_GET['tmp']);
            if( $res['code'] != 200 ){
                returnMsg($res['code'],$res['msg']);
            }
            $online = $user['online'];
        }elseif( $_GET['chat_type'] == '3' ){ //加入咨询会话
            if( $_GET['to_id'] == getUid() ){
                returnMsg(100,'to_id不能是自己的uid！');
            }
            $user = db::table('chat_user')->where('uid',$_GET['to_id'])->find();
            if( empty($user) ){
                returnMsg(100,'to_id不正确！');
            }
            if( $user['user_type'] != 2 ){
                returnMsg(100,'用户不是咨询师！');
            }
            $online = $user['online'];
        }
        if( $_GET['chat_type'] == '2' && isset($_GET['tmp']) && $_GET['tmp'] == '1' ){
            $sql = 'insert ignore into chat_tmp_session(sid,uid,to_id,chat_type,last_time)
                        values(null,?,?,?,unix_timestamp());';
        }else{
            $sql = 'insert ignore into chat_session(sid,uid,to_id,chat_type,last_time)
                        values(null,?,?,?,unix_timestamp());';
        }
        db::query($sql,[getUid(),$_GET['to_id'],$_GET['chat_type']]);
       
        returnMsg(200,'加入session成功！',[
            'online'=>$online
        ]);
    }
    /**
     * 删除聊天会话
     * $_GET[chat_type] 聊天类型
     * $_GET[to_id] 删除和谁的聊天
     * $_GET[chat_type]=0，普通聊天；删除chat_session里数据，删除消息（软删除）
     * $_GET[chat_type]=1，群聊；删除chat_session里数据
     * $_GET[chat_type]=2，客服聊天；删除chat_session里数据
     * $_GET[chat_type]=3，咨询聊天；删除chat_session里数据，删除消息（软删除）
     * 软删除说明：
     * soft_delete字段为0代表双方都没有删除；
     * soft_delete字段为1代表双方都删除了；
     * soft_delete字段等于uid，代表一方删除了；
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
        if( !in_array($_GET['chat_type'],['0','1','2','3']) ){
            returnMsg(100,'chat_type不正确！');
        }
        $chat_sessions = db::table('chat_session')->where('uid',$uid)->where('to_id',$_GET['to_id'])->where('chat_type',$_GET['chat_type'])->select();
        if( empty($chat_sessions) ){
            returnMsg(100,'会话不存在！');
        }
        $sid = 0;
        foreach( $chat_sessions as $chat_session ){
            if( $chat_session['soft_delete'] == 0 ){
                $sid = $chat_session['sid'];
            }
        }
        if( $sid == 0 ){
            returnMsg(100,'会话已删除！');
        }
        db::table('chat_session')->where('sid',$sid)->update([
            'soft_delete'=>time()
        ]);
        //退出群聊
        if( $_GET['chat_type'] == '1' ){ 
            WebsocketServerApi::roomExit($uid,$_GET['to_id']);
        }
        if( $_GET['chat_type'] == '0' || $_GET['chat_type'] == '3' ){
            db::table('chat_message')->where('uid',$uid)->where('to_id',$_GET['to_id'])->where('chat_type',$_GET['chat_type'])
            ->where('soft_delete',$_GET['to_id'])->update([
                'soft_delete'=>1
            ]);
            db::table('chat_message')->where('uid',$_GET['to_id'])->where('to_id',$uid)->where('chat_type',$_GET['chat_type'])
            ->where('soft_delete',$_GET['to_id'])->update([
                'soft_delete'=>1
            ]);
            db::table('chat_message')->where('uid',$uid)->where('to_id',$_GET['to_id'])->where('chat_type',$_GET['chat_type'])
            ->where('soft_delete',0)->update([
                'soft_delete'=>$uid
            ]);
            db::table('chat_message')->where('uid',$_GET['to_id'])->where('to_id',$uid)->where('chat_type',$_GET['chat_type'])
            ->where('soft_delete',0)->update([
                'soft_delete'=>$uid
            ]);
        }
        returnMsg(200,'删除成功！');
    }
}