<?php
namespace app\online_chat\controller;
use think\Db;
use think\facade\Request;
use think\Controller;
use app\online_chat\serverApi\WebsocketServerApi;

class Chat extends Controller{
    /**
     * 获取websocket访问的token
     */
    public function getWebsocketAccessToken(){
		//var_export($_SERVER);
        isLogin();
        //var_export(session('chat_user'));exit;
        $user = db::table('chat_user')->field('uid,user_type,name,head_img')->where('uid',session('chat_user.uid'))->where('user_type',session('chat_user.user_type'))->find(); 
        //var_export($user);exit;
        if( empty($user) ){
            returnMsg(100,'用户没有查询到！');
        }
        /*
        //update影响性能，关闭掉，由server的worker来更新用户在线状态
        $sql = 'update chat_user set online=1,last_heartbeat_time=unix_timestamp() where uid=' . session('chat_user.uid');
        db::query($sql);
        */
        if( isset($_GET['chat_type']) ){
            $chat_type = $_GET['chat_type'];
        }elseif( $user['user_type'] == '0' ){
            $chat_type = 0;
        }elseif( $user['user_type'] == '1' ){
            $chat_type = 2;
        }elseif( $user['user_type'] == '2' ){
            $chat_type = 3;
        }
        
        $sessions = db::table('chat_session')->where('chat_type',$chat_type)->where('uid',session('chat_user.uid'))->field('to_id,chat_type,0 as tmp')->select();
        
        //$session_uids = [];
        //var_dump($session_uids);exit;
        try{
            $time1 =  microtime(true) ;
            $access_token = WebsocketServerApi::get_access_token($user['uid'],$user['user_type'],$user['name'],$user['head_img'],$sessions);
        }catch( \exception $e ){
            returnMsg(100,'websocket服务未启动！');
        }
        if( $_SERVER['REQUEST_SCHEME'] == 'http' ){
            $ws = 'ws://' . $_SERVER['SERVER_NAME'] . ':2080?access_token=' . $access_token;
        }else{
            $ws = 'wss://' . $_SERVER['SERVER_NAME'] . '/online_chat_server:2080?access_token=' . $access_token;
        }
        returnMsg(200,'',[
            'userinfo'=>$user,
            'wesocket_access_token'=>$access_token,
            'ws_addr'=>$ws
        ]);
    }
    /**
     * 获取临时websocket访问的token
     */
    public function getTmpWebsocketAccessToken(){
        $session_id = session_id();
        
        $user = db::table('chat_tmp_user')->field('uid')->where('session_id',$session_id)->find();
        if( empty($user) ){
            $uid = db::table('chat_tmp_user')->insertGetId([
                'ip_addr'=>Request::ip(),
                'ctime'=>time(),
                'online'=>1,
                'last_heartbeat_time'=>0,
                'soft_delete'=>0,
                'session_id'=>$session_id
            ]);
            
            $to_id = '';
            $user = [
                'uid'=>$uid,
                'head_img'=>'',
                'tmp'=>1,
                'name'=>'',
                'to_id'=>$to_id
            ];
            $welcome = 1;
            $online = null;
        }else{
            $session = db::table('chat_session')->where('uid',$user['uid'])->where('chat_type=2')->field('uid,to_id')->find();
            //var_export($session);exit;
            if( empty($session) ){
                $to_id = '';
            }else{
                $to_id = $session['to_id'];
            }
            $user['head_img']='';
            $user['name'] = '';
            $user['tmp'] = 1;
            $welcome = 0;
            if( $to_id != '' ){
                $online = db::table('chat_user')->where('uid',$to_id)->column('online')[0];
            }else{
                $online = null;
            }
        }
        //var_export($to_id);exit;

        $access_token = WebsocketServerApi::get_access_token($user['uid'],0,'','',[],1,$to_id);
        
        if( $_SERVER['REQUEST_SCHEME'] == 'http' ){
            $ws = 'ws://' . $_SERVER['SERVER_NAME'] . ':2080?welcome='.$welcome.'&tmp=1&access_token=' . $access_token;
        }else{
            $ws = 'wss://' . $_SERVER['SERVER_NAME'] . ':2080?welcome='.$welcome.'&tmp=1&access_token=' . $access_token;
        }
        session('chat_user',$user);
        returnMsg(200,'',[
            'userinfo'=>$user,
            'wesocket_access_token'=>$access_token,
            'ws_addr'=>$ws,
            'to_id'=>$to_id,
            'online'=>$online
        ]);
    }
    /**
     * 登录
     */
    public function doLogin(){
        if( empty($_POST['app_uid']) ){
            returnMsg(100,'app_uid不能为空！');
        }
        if( empty($_POST['name']) ){
            returnMsg(100,'name不能为空！');
        }
        if( empty($_POST['head_img']) ){
            returnMsg(100,'head_img不能为空！');
        }
        
        if( !isset($_POST['user_type']) || $_POST['user_type'] == '0' ){
            $user_type = 0;

        }else{
            $user_type = $_POST['user_type'];
        }
        //进行签名验证
        if( config('chat.enable_sign') ){
            if( empty($_POST['time']) ){
                returnMsg(100,'time不能为空！');
            }
            if( $_POST['time'] < time() - 30 ){
                returnMsg(100,'time不正确！');
            }
            if( $_POST['time'] > time() + 30 ){
                returnMsg(100,'time不正确！');
            }
            if( empty($_POST['sign']) ){
                returnMsg(100,'sign不能为空！');
            }
            $params = [
                'app_uid'=>$_POST['app_uid'],
                'name'=>$_POST['name'],
                'head_img'=>$_POST['head_img'],
                'time'=>$_POST['time'],
                'user_type'=>$user_type,
                'sign_key'=>config('chat.sign_key')
            ];
            ksort($params);
            $str = http_build_query($params);
            //echo md5($str) . PHP_EOL;
            //var_dump($_POST['sign']);
            if( md5($str) != $_POST['sign'] ){
                returnMsg(100,'签名不正确！');
            }
        }
        $user = db::table('chat_user')->where('app_uid',$_POST['app_uid'])->where('user_type',$user_type)->find();
        if( empty($user) ){
            $maxUid = db::table('chat_user')->max('uid');
            if( $maxUid < 10000 ){
                $uid = 10000;
            }else{
                $uid = null;
            }
            $uid = db::table('chat_user')->insertGetId([
                'uid'=>$uid,
                'name'=>$_POST['name'],
                'head_img'=>$_POST['head_img'],
                'online'=>1,
                'last_login_time'=>time(),
                'last_heartbeat_time'=>time(),
                'user_type'=>$user_type,
                'app_uid'=>$_POST['app_uid']
            ]);
        }else{
            db::table('chat_user')->where('app_uid',$_POST['app_uid'])->update([
                'name'=>$_POST['name'],
                'head_img'=>$_POST['head_img'],
                'online'=>1,
                'last_login_time'=>time(),
                'last_heartbeat_time'=>time(),
            ]);
            $uid = $user['uid'];
        }
        session('chat_user',[
            'uid'=>$uid,
            'user_type'=>$user_type,
            'name'=>$_POST['name'],
            'head_img'=>$_POST['head_img']    
        ]);
        
        //var_dump($_SESSION);
        returnMsg(200,'登录成功！',[
            'is_mobile'=>(int)Request::isMobile(),
            'PHPSESSID'=>session_id()
        ]);
    }
    public function wxMiniProgramLogin(){
        if( !isset($_GET['code']) ){
            returnMsg(100,'code不能为空！');
        }
        if( !isset($_GET['name']) ){
            returnMsg(100,'name不能为空！');
        }
        if( !isset($_GET['head_img']) ){
            returnMsg(100,'head_img不能为空！');
        }
        $url = 'https://api.weixin.qq.com/sns/jscode2session?' . http_build_query([
            'appid'=>config('chat.weixin_miniprogram.appid'),
            'secret'=>config('chat.weixin_miniprogram.secret'),
            'js_code'=>$_GET['code'],
            'grant_type'=>'authorization_code'
        ]);
        $res = file_get_contents($url);
        $resArr = json_decode($res,true);
        if( !isset($resArr['openid']) ){
            throw new \Exception($res);
        }
        $_POST = [
            'name'=>$_GET['name'],
            'head_img'=>$_GET['head_img'],
            'time'=>time(),
            'app_uid'=>$resArr['openid']
        ];
        $this->doLogin();
    }
    public function logout(){
        if( empty($_GET['type']) || $_GET['type'] == 'destroy' ){
            //var_dump($_SESSION);
            session_destroy();
            returnMsg(200,'退出登陆成功！');
        }elseif( $_GET['type'] == 'null' ){
            //var_dump($_SESSION);
            session('chat_user',null);
            returnMsg(200,'退出登陆成功！');
        }
    }
}