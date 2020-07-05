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
        db::query('set names utf8mb4');
        if( $_GET['chat_type'] == '0' ){
            $sql = 'select a.mid,a.uid,b.name,b.head_img,chat_type,to_id,a.msg_type,a.msg,a.ctime from chat_message a 
                        left join chat_user b on a.uid=b.uid 
                        where ( a.chat_type = 0 and a.uid=' . $uid . ' and a.to_id=' . $to_id . ' and (a.soft_delete=0 or a.soft_delete = '.$to_id.') ) or
                         ( a.chat_type = 0 and a.uid=' . $to_id .' and  a.to_id=' . $uid . ' and (a.soft_delete=0 or a.soft_delete = '.$to_id.') ) 
                            order by a.mid desc limit ' . $start.','.$pageNum;
            $messages = db::query($sql);
        }elseif( $_GET['chat_type'] == '1' ){
            $sql = 'select a.mid,a.uid,b.name,b.head_img,chat_type,to_id,a.msg_type,a.msg,a.ctime from chat_message a 
                        left join chat_user b on a.uid=b.uid 
                        where (a.chat_type = 1 and a.to_id=' .$to_id. ')  order by a.mid desc limit ' . $start.','.$pageNum;
                        $messages = db::query($sql);
        }elseif( $_GET['chat_type'] == '2' ){
            //临时用户uid
            if( session('chat_user.tmp') == '1' ){
                $id = getUid();
            }else{
                $id = $to_id;
            }
            $sql = 'select a.mid,a.uid,b.name,b.head_img,chat_type,to_id,a.msg_type,a.msg,a.ctime from chat_message a 
                        left join chat_user b on a.uid=b.uid 
                        where ( a.chat_type = 2 and a.uid=' . $id . ' and tmp=1 ) or
                        ( a.chat_type = 2  and  a.to_id=' . $id .' and tmp=0 ) order by a.mid desc limit ' . $start.','.$pageNum;
            
            //echo $sql;exit;
            $messages = db::query($sql);
        }elseif( $_GET['chat_type'] == '3' ){
            $sql = 'select a.mid,a.uid,b.name,b.head_img,3 as chat_type,to_id,a.msg_type,a.msg,a.ctime from chat_message a 
                        left join chat_user b on a.uid=b.uid 
                        where ( a.chat_type = 3 and a.uid=' . $uid . ' and a.to_id=' . $to_id . ' and (a.soft_delete=0 or a.soft_delete = '.$to_id.') ) or
                         ( a.chat_type = 3 and a.uid=' . $to_id .' and  a.to_id=' . $uid .' and (a.soft_delete=0 or a.soft_delete = '.$to_id.') ) 
                         order by a.mid desc limit ' . $start.','.$pageNum;
            //echo $sql;exit;
            $messages = db::query($sql);
        }
        $mids = [];
        foreach( $messages as $message ){
            if( $message['msg'] === '' ){
                $mids[] = $message['mid'];
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