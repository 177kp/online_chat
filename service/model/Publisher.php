<?php
namespace onlineChat\model;
use onlineChat\model\Session;
/**
 * 发布者
 */
class Publisher{
    /**
     * $subscribers = [
     *     [
     *         "addr"=>[
     *              "host"=>主机地址,
     *              "port"=>端口号
     *          ],
     *          "topics"=>[
     *               [
     *                   "name"=>主题名称,
     *                   "conditions"=>[
     *                       条件1，条件2  
     *                   ]
     *               ]
     *           ],
     *           "expire_time"=>过期时间
     *     ]
     * ]
     * 
     */
    protected $subscribers = [];
    //实例
    static $instance;
    /**
     * 获取实例
     */
    static function instance(){
        if( empty(self::$instance) ){
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * 添加订阅者
     * @param array $subscriber 订阅者信息
     * [
     *     addr=>[
     *         host=>主机地址,
     *         port=>端口号
     *     ],
     *     topics=>[
     *         主题1，主题2
     *     ]
     * ]
     */
    public function addSubscriber($subscriber){
        if( !isset($subscriber['addr']) ){
            return false;
        }
        if( !isset($subscriber['addr']['host']) ){
            return false;
        }
        if( !isset($subscriber['addr']['port']) ){
            return false;
        }
        if( !isset($subscriber['topics']) ){
            return false;
        }
        foreach( $this->subscribers as $k=>$sub ){
            if( $sub['addr'] == $subscriber['addr'] && $sub['topics'] == $subscriber['topics'] ){
                $this->subscribers[$k]['expire_time'] = time()+60;
                return true;
            }
        }
        $subscriber['expire_time'] = time()+60;
        $this->subscribers[] = $subscriber;
        return true;
    }
    /**
     * 发布消息
     * @param array $data 消息
     * [
     *     topic=>主题名称，
     *     msg=>消息内容
     * ]
     */
    public function publish($data){
        static $socket;

        global $workerProcess;
        if( !isset($data['topic']) ){
            throw new \Exception('data[topic]不存在！');
        }
        if( !isset($data['msg']) ){
            throw new \Exception('data[msg]不存在！');
        }
        if( empty($socket) ){
            $socket = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
        }

        $buffer = json_encode($data,JSON_UNESCAPED_UNICODE);
        if( isset($workerProcess['pipes'][0]) ){
            fwrite( $workerProcess['pipes'][0],$buffer . "\n");
        }
        //socket_write($workerProcess['socket'],$buffer);
        $len = strlen($buffer);
        //echo $topicName . json_encode($msg) . PHP_EOL;
        foreach( $this->subscribers as $k=>$subscriber ){
            if( $subscriber['expire_time'] < time() ){
                unset($this->subscribers[$k]);
            }

            foreach( $subscriber['topics'] as $topic ){
                //var_dump($topic['name'],$data['topic']);
                if( $topic['name'] != 'all' && $topic['name'] != $data['topic'] ){
                    continue;
                }
                if( !empty($topic['conditions']) ){
                    $meetingCondition = 1; // 是否满足条件，0-不满足，1-满足
                    foreach( $topic['conditions'] as $column=>$value ){
                        if( $this->meetingCondition($column,$value,$data['msg']) == false ){
                            $meetingCondition = 0;
                            break;
                        }
                    }
                    if( $meetingCondition == 0 ){
                        continue;
                    }
                }
                //发送消息
                socket_sendto($socket, $buffer, $len, 0, $subscriber['addr']['host'], $subscriber['addr']['port']);
                //echo $subscriber['addr']['host'] . ':' . $subscriber['addr']['port'] . ',' . json_encode($data['msg']) . PHP_EOL;
                break;
            }
        }
    }
    /**
     * 判断是否满足条件
     * @param string $column 字段名称
     * @param mixed $value 值
     * @param array $msg
     * @return boolean
     */
    protected function meetingCondition($column,$value,$msg){
        switch( $column ){
            case 'uid':
                if( isset($msg['uid']) && $msg['uid'] == $value  ){
                    return true;
                }elseif( isset($msg['chat_type']) && $msg['chat_type'] == 0 && isset($msg['to_id']) && $msg['to_id'] == $value ){
                    return true;
                }else{
                    return false;
                }
                break;
            case 'room_id':
                if( isset($msg['chat_type']) && $msg['chat_type'] == 1 && isset($msg['to_id']) && $msg['to_id'] == $value ){
                    return true;
                }else{
                    return false;
                }
                break;
            case 'default':
                return false;
                break;
        }
    }
}