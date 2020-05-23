<?php
namespace onlineChat\server\http;
use onlineChat\model\Session;
use onlineChat\model\Publisher as PublisherModel;

class PublisherApi{
    /**
     * 添加订阅者
     * @param array $get HTTP的get参数
     */
    static function addSubscriber($get){

        //safe_dump($get);
        if( empty($get['subscriber']) ){
            return httpApiMsg(100,'subscriber不能为空！');
        }
        $subscriber = $get['subscriber'];
        //safe_dump($subscriber);
        if( !isset($subscriber['addr']) ){
            return httpApiMsg(100,'addr参数不存在！');
        }
        if( !isset($subscriber['addr']['host']) ){
            return httpApiMsg(100,'addr[host]参数不存在！');
        }
        if( !isset($subscriber['addr']['port']) ){
            return httpApiMsg(100,'addr[port]参数不存在！');
        }
        if( !isset($subscriber['topics']) ){
            return httpApiMsg(100,'topics参数不存在！');
        }
        //var_dump($subscriber['topics']);
        foreach( $subscriber['topics'] as $k=>$topic ){
            if( !isset($topic['name']) ){
                return httpApiMsg(100,'topics[][name]参数不存在！');
            }
            if( !isset($topic['conditions']) ){
                $subscriber[$k]['conditions'] = [];
            }
        }
        PublisherModel::instance()->addSubscriber($subscriber);
        return httpApiMsg(200,'添加发布者成功！');
    }
}