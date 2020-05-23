<?php
/**
 * online chat 配置文件
 */
return [
    //是否启用签名认证，true-是，false否；建议启用
    'enable_sign'=>false,
    //签名key,建议新应用修改签名key
    'sign_key'=>'bc7a61ca314a0e0a7aa29d218d70d039',  
    //websocket和http服务程序配置
    'server'=>[ 
        //server的内存大小限制
        'memory_limit'=>'512M',
        //server主机地址
        'host'=>'0.0.0.0',
        //server的http主机地址
        'http_host'=>'127.0.0.1',
        //websocket端口号
        'websocket_port'=>2080,
        //http端口号
        'http_port'=>3080,
        //打印内存信息
        'dump_memory'=>1
    ],
    //微信小程序配置
    'weixin_miniprogram'=>[
        'appid'=>'',
        'secret'=>''
    ]
];