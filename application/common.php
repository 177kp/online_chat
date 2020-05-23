<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
function returnMsg($code,$msg,$data = []){
    header("content:application/json;chartset=uft-8");
    echo json_encode([
        'code'=>$code,
        'msg'=>$msg,
        'data'=>$data
    ],JSON_UNESCAPED_UNICODE);
    exit;
}
function isLogin(){
    if( empty(session('chat_user')) ){
        returnMsg(100,'未登录！',[
            'isLogin'=>0
        ]);
    }
    return true;
}
function getUid(){
    return session('chat_user.uid');
}
function imageToGray($imgSrc,$imgDst){
    $im = imagecreatefromjpeg($imgSrc);
    if ($im && imagefilter($im, IMG_FILTER_GRAYSCALE)) {
        imagejpeg($im, $imgDst);
    }
}
function allowCrossDomain($domain = '*'){
    if( isset($_SERVER['HTTP_ORIGIN']) && $domain == '*' ){
        $domain =  $_SERVER['HTTP_ORIGIN'];
    }
    // 允许 $originarr 数组内的 域名跨域访问
    header('Access-Control-Allow-Origin:' . $domain);
    // 响应类型
    header('Access-Control-Allow-Methods:*');
    // 带 cookie 的跨域访问
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers:token');
    if( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS' ){
        exit;
    }
}