<?php
namespace onlineChat\lib\protocol;
use onlineChat\lib\websocketServer\ConnectionBase;
/**
 * WebSocket
 * websocket协议（rfc6455）的简单解析类
 */
class Websocket{
    /**
     * @const string GUID
     */
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    /**
     * @const int PAYLOAD_MAX_LENGTH frame的payload_length最大长度
     */
    const PAYLOAD_MAX_LENGTH = 65535;
    /**
     * @var Websocket $instance 实例
     */
    static $instance;
    /**
     * @var array $frames 收到的frame数组
     * [
     *     fd1=>$frame, //frame不是完整的，是完整的已被unset
     *     fd2=>$frame,
     *     fd3=>$frame
     * ]
     */
    protected $frames = [];
    /**
     * 获取实例
     * @return self
     */
    static function instance(){
        if( empty(self::$instance) ){
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * WebSocket::Handshake()
     * websocket握手
     * @param ConnectionBase $Connection
     * @return $request
     * [
     *    method=>get,
     *    uri=>,
     *    protocol=>,
     *    ...
     * ]
     */
    public function Handshake( ConnectionBase $Connection ){
        $request = [];
        $handle = $Connection->read(2000); 
        //safe_dump($handle);
        $arr = \explode("\r\n\r\n",$handle);
        $items = \explode("\r\n",$arr[0]);
        //safe_dump($items);
        $res = explode(" ",$items[0]);
        $request['method'] = $res[0];
        if( $request['method'] != 'GET' ){
            throw new \Exception('http方法不是GET');
        }
        if( !isset($res[1]) ){
            throw new \Exception('uri不存在！');
        }
        $request['uri'] = $res[1];
        if( !isset($res[2]) ){
            throw new \Exception('protocol不存在！');
        }
        $request['protocol'] = $res[2];
        if( $request['protocol'] != 'HTTP/1.1' ){
            throw new \Exception('协议版本不正确！');
        }
        array_shift($items);
        //解析HTTP头部字段
        foreach( $items as $item ){
            $info = \explode(':',$item);
            $request[trim($info[0])] = trim($info[1]);
        }
        if( !isset($request['Connection']) || $request['Connection'] != 'Upgrade' ){
            throw new \Exception('Connection字段不正确！');
        }
        if( !isset($request['Upgrade']) || $request['Upgrade'] != 'websocket' ){
            throw new \Exception('Upgrade字段不正确！');
        }
        if( !isset($request['Sec-WebSocket-Version']) || $request['Sec-WebSocket-Version'] != 13 ){
            throw new \Exception('Sec-WebSocket-Version字段不正确！');
        }
        if( !isset($request['Sec-WebSocket-Key']) ){
            throw new \Exception('Sec-WebSocket-Key字段不存在！');
        }
        //safe_dump($request);
        //发送hanshake响应头
        $handshake = [];
        $handshake[] = "HTTP/1.1 101 Switching Protocols";
        $handshake[] = "Upgrade: websocket";
        $handshake[] = "Connection: Upgrade";
        //safe_dump( $request['Sec-WebSocket-Key'] );
        $key = base64_encode(sha1($request['Sec-WebSocket-Key'] . self::GUID , true));
        $handshake[] = "Sec-WebSocket-Accept: " . $key;
        $handshake = implode("\r\n",$handshake) . "\r\n\r\n";
        $Connection->write($handshake);
        return $request;
    }
    /**
     * WebSocket::readFrame()
     * @param ConnectionBase $Connection
     * Read a websocket data
     * @return $frame | false | null
     */
    public function readFrame(ConnectionBase $Connection){
        /**
         * ws-frame = frame-fin           ; 1 bit in length
         *            frame-rsv1          ; 1 bit in length
         *            frame-rsv2          ; 1 bit in length
         *            frame-rsv3          ; 1 bit in length
         *            frame-opcode        ; 4 bits in length
         *            frame-masked        ; 1 bit in length
         *            frame-payload-length   ; either 7, 7+16,
         *                                   ; or 7+64 bits in
         *                                   ; length
         *            [ frame-masking-key ]  ; 32 bits in length
         *            frame-payload-data     ; n*8 bits in
         *                                   ; length, where
         *                                   ; n >= 0
         */
        $fd = $Connection->fd;
        if( !isset($this->frames[$fd]['payload-data']) ){
            //$firstChar = $Connection->read(1);
            $firstChar = $this->read($Connection,1,true);
            if( $this->frames[$fd]['repeat-count'] > 1 ){
                unset($this->frames[$fd]);
                return;
            }
            if( $firstChar === false ){
                return;
            }
            $handle        = ord($firstChar);
            $frame        = [];
            /**
             * frame-fin = %x0 ; more frames of this message follow
             *           / %x1 ; final frame of this message
             *                 ; 1 bit in length
             */
            $frame['fin']    = ($handle >> 7) & 0x1 ;
            /**
             * frame-rsv1 = %x0 / %x1
             *              ; 1 bit in length, MUST be 0 unless
             *              ; negotiated otherwise
             */
            $frame['rsv1']   = ($handle >> 6) & 0x1;
            /**
             * frame-rsv2 = %x0 / %x1
             *              ; 1 bit in length, MUST be 0 unless
             *              ; negotiated otherwise
             */
            $frame['rsv2']   = ($handle >> 5) & 0x1;
            /**
             * frame-rsv3 = %x0 / %x1
             *              ; 1 bit in length, MUST be 0 unless
             *              ; negotiated otherwise
             */
            $frame['rsv3']   = ($handle >> 4) & 0x1;
            if (0x0 !== $frame['rsv1'] || 0x0 !== $frame['rsv2'] || 0x0 !== $frame['rsv3']) {
                //var_export($firstChar);
                //var_export($frame);
                //var_export($Connection->read(1000));
                throw new \Exception(
                    sprintf('Get rsv1: %s, rsv2: %s, rsv3: %s, they all must be equal to 0.',
                    2,$frame['rsv1'], $frame['rsv2'], $frame['rsv3'])
                );
            }
            /**
             * frame-opcode            = frame-opcode-non-control /
             *                           frame-opcode-control /
             *                           frame-opcode-cont
             * 
             * frame-opcode-cont       = %x0 ; frame continuation
             * 
             * frame-opcode-non-control= %x1 ; text frame
             *                         / %x2 ; binary frame
             *                         / %x3-7
             *                         ; 4 bits in length,
             *                         ; reserved for further non-control frames
             * 
             * frame-opcode-control    = %x8 ; connection close
             *                         / %x9 ; ping
             *                         / %xA ; pong
             *                         / %xB-F ; reserved for further control
             *                         ; frames
             *                         ; 4 bits in length
             * 
             */
            $frame['opcode'] =  $handle       & 0xf;
            switch( $frame['opcode'] ){
                case 0x0:  //frame continuation

                case 0x1: //text frame

                case 0x2: //binary frame
                case 0x8: //connection close
                case 0x9: //ping
            }
            
            /**
             *   frame-masked            = %x0
             *                           ; frame is not masked, no frame-masking-key
             *                           / %x1
             *                           ; frame is masked, frame-masking-key present
             *                           ; 1 bit in length
             *
             *   frame-payload-length    = ( %x00-7D )
             *                           / ( %x7E frame-payload-length-16 )
             *                           / ( %x7F frame-payload-length-63 )
             *                           ; 7, 7+16, or 7+64 bits in length,
             *                           ; respectively
             *
             *   frame-payload-length-16 = %x0000-FFFF ; 16 bits in length
             *
             *   frame-payload-length-63 = %x0000000000000000-7FFFFFFFFFFFFFFF
             *                           ; 64 bits in length
             */
            $handle = $this->read($Connection,1);
            if( $handle === false ){
                return;
            }
            $handle        = ord($handle);
            $frame['masked']   = ($handle >> 7) & 0x1;
            $frame['payload-length'] =  $handle & 0x7f;
            switch( $frame['payload-length'] ){
                case 0x0:
                    $frame['payload-data'] = '';
                    // Consume the whole frame.
                    if (0x1 === $frame['masked']) {
                        $handle = $this->read($Connection,4);
                        if( $handle === false ){
                            return;
                        }
                        $frame['masking-key'] = $handle;
                    }
                    return $frame;
                case 0x7e:
                    $handle = $this->read($Connection,2);
                    if( $handle === false ){
                        return;
                    }
                    if( strlen($handle) != 2 ){
                        throw new \Exception('read数据失败！');
                    }
                    $handle = unpack('nl', $handle);
                    $frame['payload-length-16'] = $handle['l'];
                    if( $frame['payload-length-16']>self::PAYLOAD_MAX_LENGTH || $frame['payload-length-16'] <0 ){
                        throw new \Exception('payload-length-16超过'.self::PAYLOAD_MAX_LENGTH.'！');
                    }
                    break;
                case 0x7f:
                    $handle = $this->read($Connection,8);
                    if( $handle === false ){
                        return;
                    }
                    if( strlen($handle) != 8 ){
                        throw new \Exception('read数据失败！');
                    }
                    $handle = unpack('N*l',$handle);
                    $frame['payload-length-63'] = $handle['l2'];
                    if ( $frame['payload-length-63'] > 0x7fffffffffffffff) {
                        throw new \Exception('payload-length-63超过0x7fffffffffffffff！');
                    }
                    if( $frame['payload-length-63']>self::PAYLOAD_MAX_LENGTH || $frame['payload-length-63'] <0 ){
                        throw new \Exception('payload-length-63超过'.self::PAYLOAD_MAX_LENGTH.'！');
                    }
                    break;
            }
            /**
             * frame-masking-key       = 4( %x00-FF )
             *                         ; present only if frame-masked is 1
             *                         ; 32 bits in length
             */
            if( 0x1 === $frame['masked'] ){
                $handle = $this->read($Connection,4);
                if( $handle === false ){
                    return;
                }
                $frame['masking-key'] = $handle;
                if ( strlen($frame['masking-key']) < 4 ) {
                    throw new \Exception('masking-key太短了');
                }
            }
            $frame['payload-data'] = '';
            $this->frames[$fd] = $frame;
        }else{
            $frame = $this->frames[$fd];
        }

        /**
         * frame-payload-data      = (frame-masked-extension-data
         *                            frame-masked-application-data)
         *                          ; when frame-masked is 1
         *                         / (frame-unmasked-extension-data
         *                           frame-unmasked-application-data)
         *                          ; when frame-masked is 0
         */
        if( isset($frame['payload-length-63']) ){
            $length = $frame['payload-length-63'] - strlen($frame['payload-data']);
        }elseif( isset($frame['payload-length-16']) ){
            $length = $frame['payload-length-16'] - strlen($frame['payload-data']);
        }else{
            $length = $frame['payload-length'] - strlen($frame['payload-data']);
        }
        do{
            $readLength = min( 1024, $length );
            $handle = $Connection->read($readLength);
            if( strlen($handle) == 0 ){
                //var_export( $readLength ) . '--------------------------' . PHP_EOL;
                return;
            }
            $this->frames[$fd]['payload-data'] .= $handle;
        }while($length -= strlen($handle) );
        if (0x0 === $frame['masked']) { //没有掩码
            //var_dump($length);
            $frame = $this->frames[$fd];
            unset($this->frames[$fd]);
            //var_dump( $frame);
            return $frame;
        }elseif( 0x1 === $frame['masked'] ){ //有掩码
            $frame = $this->frames[$fd];
            $dataLength = \strlen($frame['payload-data']);
            $maskingKey = $this->frames[$fd]['masking-key'];
            $masks = \str_repeat($maskingKey, \floor($dataLength / 4)) . \substr($maskingKey, 0, $dataLength % 4);
            $frame['payload-data'] = $frame['payload-data'] ^ $masks;
            unset($this->frames[$fd]);
            $frame['masking-key'] = '0x' . strtoupper( bin2hex($maskingKey) );
            //var_dump($frame);
            return $frame;
        }
    }
    /**
     * 读取websocket头部；当websocket头部在多个数据包里面，需要缓存头部数据
     * @param ConnectionBase $Connection
     * @param int $length 长度
     * @param boolean $resetSeat 重置读取websocket头部的位置为0
     * @return mixed $handle 读取的字符串 | false 读取字符串失败
     */
    protected function read( ConnectionBase $Connection,$length,$resetSeat = false ){
        $fd = $Connection->fd;
        if( !isset($this->frames[$fd]) ){
            $this->frames[$fd] = [
                'frame-head'=>'',
                'read-head-seat'=>0,
                'repeat-count'=>0
            ];
        }else{
            if( $resetSeat ){
                $this->frames[$fd]['read-head-seat'] = 0;
                $this->frames[$fd]['repeat-count']++;
            }
        }
        $handle = substr($this->frames[$fd]['frame-head'],$this->frames[$fd]['read-head-seat'],$length);
        if( strlen($handle) == $length ){
            $this->frames[$fd]['read-head-seat'] += strlen($handle);
            return $handle;
        }else{
            $this->frames[$fd]['frame-head'] .= $Connection->read( $length -strlen($handle) );
            $handle = substr($this->frames[$fd]['frame-head'],$this->frames[$fd]['read-head-seat'],$length);
            if( strlen($handle) == $length ){
                $this->frames[$fd]['read-head-seat'] += strlen($handle);
                return $handle;
            }else{
                return false;
            }
        }
    }
    /**
     * 清除缓存的frame
     * @param $fd;
     */
    public function clearFrameByFd($fd){
        unset($this->frames[$fd]);
    }
        
    /**
     * WebSocket::genFrame()
     * 生成websocket一帧的数据
     * @param array $frame
     * [
     *     'fin'=>0x1, //默认是0x1
     *     'rsv1'=>0x0, //默认是0x0
     *     'rsv2'=>0x0, //默认是0x0
     *     'rsv3'=>0x0, //默认是0x0
     *     'opcode'=>0x1, //默认是0x1
     *     'masked'=>0x0, //默认是0x0,0x0是服务的向客户端发送的包（不需要掩码），0x1是客户端向服务端发送的包（需要掩码）
     *     'payload-data'=>数据 //必填
     * ]
     * @return websocket的frame
     */
    public function genFrame($frame) {
        if( !isset($frame['payload-data']) ){
            throw new \Exception('payload-data参数不存在！');
        }
        if( !isset($frame['fin']) ){
            $frame['fin'] = 0x1;
        }
        if( !isset($frame['rsv1']) ){
            $frame['rsv1'] = 0x0;
        }
        if( !isset($frame['rsv2']) ){
            $frame['rsv2'] = 0x0;
        }
        if( !isset($frame['rsv3']) ){
            $frame['rsv3'] = 0x0;
        }
        if( !isset($frame['opcode']) ){
            $frame['opcode'] = 0x1;
        }
        //生成第一个字节
        $outBuffer    = \chr(
            ($frame['fin']  << 7)
          | ($frame['rsv1'] << 6)
          | ($frame['rsv2'] << 5)
          | ($frame['rsv3'] << 4)
          | $frame['opcode']
        );

        if( !isset($frame['masked']) ){
            $frame['masked'] = 0x0;
        }
        $length = \strlen($frame['payload-data']);
        
        if( $length >= 0x0 && $length < 0x7e ){
            $outBuffer .= \chr(($frame['masked'] << 7) | $length);
        }elseif( $length >= 0x7e && $length < 0xffff ){
            $outBuffer .= \chr(($frame['masked'] << 7) | 0x7e) . \pack('n', $length);
        }elseif( $length >= 0xffff && $length <= 0x7fffffffffffffff ){
            $outBuffer .= \chr(($frame['masked'] << 7) | 0x7f) . \pack('NN', 0, $length);
        }else{
            throw new \Exception('payload-data长度大于0x7fffffffffffffff！');
        }
        
        if (0x0 === $frame['masked'] ) {
            $outBuffer .= $frame['payload-data'];
        } else {
            $maskingKey = $this->genMaskingKey();
            $dataLength = strlen($frame['payload-data']);
            $masks = \str_repeat($maskingKey, \floor($dataLength / 4)) . \substr($maskingKey, 0, $dataLength % 4);
            //safe_dump($masks);
            $frame['payload-data'] = $frame['payload-data'] ^ $masks;

            $outBuffer .= $maskingKey;
            $outBuffer .= $frame['payload-data'];
            //var_export( base64_encode($outBuffer) );exit;
        }
        return $outBuffer;
    }
    /**
     * 生成websocket握手
     * @param string $host 主机地址
     * @param int $port 端口号
     * @param string $uri uri
     */
    public function genHandShakeRequest($host, $port,$uri="/",$randKey){
        $requestHeader =  "GET $uri HTTP/1.1" . "\r\n" .
            "Origin: null" . "\r\n" .
            "Host: {$host}:{$port}" . "\r\n" .
            "Sec-WebSocket-Key: {$randKey}" . "\r\n" .
            "User-Agent: SwooleWebsocketClient"."/0.1.4" . "\r\n" .
            "Upgrade: websocket" . "\r\n" .
            "Connection: Upgrade" . "\r\n" .
            "Sec-WebSocket-Protocol: wamp" . "\r\n" .
            "Sec-WebSocket-Version: 13" . "\r\n" . "\r\n";
        return $requestHeader;
    }
    /**
     * 验证http是否升级
     */
    public function verifyUpgrade( $handle,$randKey ){
        $arr = \explode("\r\n\r\n",$handle);
        $items = \explode("\r\n",$arr[0]);
        //safe_dump($items);
        $res = explode(" ",$items[0],3);
        //var_dump($res);
        $response['protocol'] = $res[0];
        if( $response['protocol'] != 'HTTP/1.1' ){
            throw new \Exception('协议不正确！');
        }
        if( !isset($res[1]) || $res[1] != 101 ){
            throw new \Exception('code不正确！');
        }
        $response['code'] = $res[1];
        if( !isset($res[2]) || $res[2] != 'Switching Protocols' ){
            throw new \Exception('Switching Protocols不正确！');
        }
        $response['action'] = $res[2];
        array_shift($items);
        //解析HTTP头部字段
        foreach( $items as $item ){
            $info = \explode(':',$item);
            $response[trim($info[0])] = trim($info[1]);
        }
        if( !isset($response['Upgrade']) || $response['Upgrade'] != 'websocket' ){
            throw new \Exception('Upgrade字段不正确！');
        }
        //安全验证
        $sec_websocket_key = base64_encode(sha1($randKey . self::GUID , true));
        //var_dump($sec_websocket_key,$response);
        if( !isset($response['Sec-WebSocket-Accept']) || $response['Sec-WebSocket-Accept'] != $sec_websocket_key ){
            throw new \Exception('Sec-WebSocket-Accept字段不正确！');
        }
        return $response;
    }
    public function getFrameCount(){
        //var_dump($this->frames);
        return count($this->frames);
    }
    /**
     * WebSocket::genMaskingKey()
     * 生成4字节随机的掩码
     * @return
     */
    public function genMaskingKey(){
        return chr(\mt_rand(1,255)) . chr(\mt_rand(1,255)) . chr(\mt_rand(1,255)) . chr(\mt_rand(1,255));
    }
}