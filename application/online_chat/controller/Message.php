<?php
namespace app\online_chat\controller;
use think\Db;
use think\Request;
use onlineChat\model\Message as MessageModel;
class Message{
    /*
     * 聊天记录
     * $_GET[to_id] 和谁聊天id
     * $_GET[chat_type] 聊天类型
     * $_GET[page] 分页
     * $_GET[pageNum] 每页多少条
     */
    public function index(){
        $uid = getUid();
        if( empty($_GET['to_id']) ){
            returnMsg(100,'to_id不能为空！');
        }
        if( !isset($_GET['chat_type']) ){
            returnMsg(100,'chat_type不存在！');
        }
        if( !in_array($_GET['chat_type'],['0','1','2','3']) ){
            returnMsg(100,'chat_type不正确！');
        }
        //分页相关数据
        if( empty($_GET['page']) || $_GET['page'] < 1 ){
            $page = 1;
        }else{
            $page = $_GET['page'];
        }
        if( empty($_GET['pageNum']) || $_GET['pageNum'] < 1 ){
            $pageNum = 20;
        }else{
            $pageNum = (int)$_GET['pageNum'];
        }
        if( $pageNum > 500 ){
            returnMsg(100,'参数pageNum不能大于500！');
        }
        $start = ($page - 1) * $pageNum;

        $to_id = (int)$_GET['to_id'];

        if( $_GET['chat_type'] == '1' ){
            //判断用户是否加入了群聊
            $chat_session = db::table('chat_session')->where('uid',$uid)->where('to_id',$_GET['to_id'])->where('chat_type=1 and soft_delete=0')->find();
            if( empty($chat_session) ){
                returnMsg(100,'to_id不正确！');
            }
        }

        db::query('set names utf8mb4');
        if( $_GET['chat_type'] == '0' ){
            $sql = 'select mid,uid,tmp,chat_type,to_id,msg_type,msg,ctime from chat_message
                        where ( chat_type = 0 and uid=' . $uid . ' and to_id=' . $to_id . ' and (soft_delete=0 or soft_delete = '.$to_id.') ) or
                         ( chat_type = 0 and uid=' . $to_id .' and  to_id=' . $uid . ' and (soft_delete=0 or soft_delete = '.$to_id.') ) 
                            order by mid desc limit ' . $start.','.$pageNum;
            $messages = db::query($sql);
        }elseif( $_GET['chat_type'] == '1' ){
            $sql = 'select mid,uid,tmp,chat_type,to_id,msg_type,msg,ctime from chat_message 
                        where (chat_type = 1 and to_id=' .$to_id. ')  order by mid desc limit ' . $start.','.$pageNum;
                        $messages = db::query($sql);
        }elseif( $_GET['chat_type'] == '2' ){
            /**
             * @var $tmp 是否是临时会话
             */
            if( !isset($_GET['tmp']) ){
                $tmp = 1;
            }else{
                $tmp = $_GET['tmp'];
            }
            //用户uid
            if( session('chat_user.tmp') == '1' || session('chat_user.user_type') != '1' ){
                $id = getUid();
            }else{
                $id = $to_id;
            }
            if( $tmp == 0 ){
                $sql = 'select mid,uid,tmp,chat_type,to_id,msg_type,msg,ctime,tmp from chat_message
                            where ( chat_type = 2 and uid=' . $id . ' and tmp=0 ) or
                            ( chat_type = 2  and  to_id=' . $id .' and tmp=0 ) order by mid desc limit ' . $start.','.$pageNum;
            }else{
                $sql = 'select mid,uid,tmp,chat_type,to_id,msg_type,msg,ctime from chat_message
                            where ( chat_type = 2 and uid=' . $id . ' and tmp=1 ) or
                            ( chat_type = 2  and  to_id=' . $id .' and tmp=2 ) order by mid desc limit ' . $start.','.$pageNum;
            }
            //echo $sql;exit;
            $messages = db::query($sql);
        }elseif( $_GET['chat_type'] == '3' ){
            $sql = 'select mid,uid,tmp,chat_type,to_id,msg_type,msg,ctime from chat_message
                        where ( chat_type = 3 and uid=' . $uid . ' and to_id=' . $to_id . ' and (soft_delete=0 or soft_delete = '.$to_id.') ) or
                         ( chat_type = 3 and uid=' . $to_id .' and to_id=' . $uid .' and (soft_delete=0 or soft_delete = '.$to_id.') ) 
                         order by mid desc limit ' . $start.','.$pageNum;
            //echo $sql;exit;
            $messages = db::query($sql);
        }
        $mids = [];
        $uids = [];
        foreach( $messages as $message ){
            if( $message['msg'] === '' ){ //大文本
                $mids[] = $message['mid'];
            }
            if( $message['tmp'] == 0 || $message['tmp'] == '2' ){
                $uids[] = $message['uid'];
            }
        }
        if( !empty($uids) ){
            $users = db::table('chat_user')->field('uid,name,head_img')->where('uid','in',$uids)->where('soft_delete=0')->select();
            $arr = [];
            foreach( $users as $user ){
                $arr[$user['uid']] = $user;
            }
            foreach( $messages as $k=>$message ){
                if( $message['tmp'] == 0 || $message['tmp'] == '2' ){
                    if( isset($arr[$message['uid']]) ){
                        $messages[$k]['name'] = $arr[$message['uid']]['name'];
                        $messages[$k]['head_img'] = $arr[$message['uid']]['head_img'];
                    }else{
                        $messages[$k]['name'] = '';
                        $messages[$k]['head_img'] = '';
                    }
                }else{
                    $messages[$k]['name'] = '';
                    $messages[$k]['head_img'] = '';
                }
            }
        }
        if( !empty($mids) ){
            $messageTexts  = db::table('chat_message_text')->where('message_id','in',$mids)->select();
            $tmpMessageTexts = [];
            foreach( $messageTexts as $messageText ){
                $tmpMessageTexts[$messageText['message_id']] = $messageText;
            }
            foreach( $messages as $k=>$message ){
                if( isset($tmpMessageTexts[$message['mid']]) ){
                    $messages[$k]['msg'] = $tmpMessageTexts[$message['mid']]['content'];
                }
            }
        }
        foreach( $messages as $k=>$message ){
            if( $message['msg_type'] == MessageModel::MSG_TYPE_FILE  ){
                $messages[$k]['msg'] = json_decode($message['msg'],true);
                if( $messages[$k]['msg'] == false ){
                    $messages[$k]['msg'] = [
                        'filename'=>'',
                        'filesize'=>0,
                        'path'=>'#'
                    ];
                }
            }
            if( $message['msg_type'] == MessageModel::MSG_TYPE_SOUND  ){
                $messages[$k]['msg'] = json_decode($message['msg'],true);
                if( $messages[$k]['msg'] == false ){
                    $messages[$k]['msg'] = [
                        'duration'=>0,
                        'path'=>'#'
                    ];
                }
            }
            if( $message['msg_type'] == MessageModel::MSG_TYPE_VIDEO  ){
                $messages[$k]['msg'] = json_decode($message['msg'],true);
                if( $messages[$k]['msg'] == false ){
                    $messages[$k]['msg'] = [
                        'duration'=>0,
                        'video_cover_img'=>'/static/img/video-cover.jpg',
                        'path'=>'#'
                    ];
                }
            }
        }
        db::query('set names utf8');
        $messages = array_reverse($messages);
        returnMsg(200,'获取历史消息成功！',$messages);
    }
}