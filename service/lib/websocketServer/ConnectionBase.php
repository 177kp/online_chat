<?php
namespace onlineChat\lib\websocketServer;
abstract class ConnectionBase{
    /**
     * @var int $fd 链接的fd
     */
    public $fd;
    /**
     * 读取流
     * @param int $length 读取最大长度
     */
    abstract public function read($length);
    /**
     * 写入流
     * @param string $buffer 字符串 
     */
    abstract public function write($buffer);
    /**
     * 从流中复制一段字符串
     * @param int $start 开始位置
     * @param int $length 长度
     */
    abstract public function substr($start,$length);
}