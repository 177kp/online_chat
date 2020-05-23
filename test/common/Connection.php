<?php
namespace test\common;
use onlineChat\lib\websocketServer\ConnectionBase;
class Connection extends ConnectionBase{
    
    protected $stream = '';
    protected $outStream = '';
    public function __construct($fd = null){
        if( empty($fd) ){
            $this->fd = mt_rand(1000,65535);
        }else{
            $this->fd = $fd;
        }
        
    }
    /**
     * 读取流
     * @param int $length 读取最大长度
     */
    public function read($length){
        $stream = $this->stream;
        $this->stream = substr($this->stream,$length);
        //var_dump($this->stream);
        return substr($stream,0,$length);
    }
    /**
     * 写入流
     * @param string $buffer 字符串 
     */
     public function write($buffer){
         $this->outStream .= $buffer;
     }
     public function inputStream($buffer){
        $this->stream .= $buffer;
     }
     public function substr($start,$length){
         return substr($this->stream,$start,$length);
     }
     public function outStream($length){
        $outStream = $this->outStream;
        $this->outStream = substr($this->outStream,$length);
        //var_dump($this->stream);
        return substr($outStream,0,$length);
     }
}