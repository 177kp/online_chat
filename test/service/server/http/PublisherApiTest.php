<?php
use PHPUnit\Framework\TestCase;
use onlineChat\server\http\PublisherApi;
use onlineChat\model\Publisher;
include_once __DIR__ . '/../../../init.php';
class PublisherApiTest extends TestCase{
    public function test_addSubscriber(){
        $get = [
            'subscriber'=>[
                'addr'=>[
                    'host'=>'127.0.0.1',
                    'port'=>15555
                ],
                'topics'=>[
                    [
                        'name'=>'message',
                        'conditions'=>[]
                    ]
                ]
            ]
        ];
        $res = PublisherApi::addSubscriber($get);
        $res = json_decode($res,true);
        $this->assertEquals($res['code'],200);
        $str = var_export(Publisher::instance(),true);
        //echo $get['subscriber']['addr']['port'];
        $str = strstr($str,(string)$get['subscriber']['addr']['port']);
        //var_dump($str);
        $this->assertTrue( strlen($str)>0  );
    }
}