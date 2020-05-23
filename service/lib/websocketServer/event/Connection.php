<?php
namespace onlineChat\lib\websocketServer\event;
use onlineChat\lib\websocketServer\ConnectionBase;
/**
 * EventBufferEvent的连接类
 */
class Connection extends ConnectionBase{
    /**
     * @var EventBufferEvent $EventBufferEvent
     */
    protected $EventBufferEvent;
    /**
     * 实例化
     * @param EventBufferEvent $EventBufferEvent
     */
    public function __construct(\EventBufferEvent $EventBufferEvent){
        $this->EventBufferEvent = $EventBufferEvent;
        $this->fd = $EventBufferEvent->fd;
    }
    /**
     * 读取流
     * @param int $length 读取长度
     */
    public function read($length){
        return $this->EventBufferEvent->input->read($length);
    }
    /**
     * 向流写入
     * @param string $buffer
     */
    public function write($buffer){
        //safe_dump('发送：'.$buffer);
        if( !empty($this->EventBufferEvent->output) ){
            return $this->EventBufferEvent->output->add($buffer);
        }
    }
    /**
     * 从流中复制一段字符串
     * @param int $start 开始位置
     * @param int $length 长度
     */
    public function substr($start,$length){
        return $this->EventBufferEvent->input->substr($start,$length);
    }
    public function __destruct(){
        //safe_dump('连接实例被销毁');
    }
}