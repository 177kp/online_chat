<?php
namespace onlineChat\lib;
//兼容windows
/**
 * @const SIGTERM 15 进程信号
 */
!defined('SIGTERM') && define('SIGTERM',15);
/**
 * @const SIGUSR1 10 进程信号
 */ 
!defined('SIGUSR1') && define('SIGUSR1',10);
/**
 * @const SIGUSR2 12 进程信号
 */ 
!defined('SIGUSR2') && define('SIGUSR2',12); 
/**
 * 守护进程
 */
class Daemon{
    /**
     * @var $EventBase EventBase
     */
    private $EventBase;
    /**
     * @var $serverScriptFile server的脚本文件
     */
    private $serverScriptFile;
    /**
     * @var $TimerEvents 事件数组
     */
    private $TimerEvents;
    /**
     * @var $descriptorspec 进程通信描述符
     */
    private $descriptorspec;
    /**
     * @var $pipes 进程通信句柄
     */
    private $pipes = [];
    /**
     * @var $process 进程资源
     */
    private $process = null;
    /**
     * @var $pidFile 守护进程pid文件
     */
    private $pidFile = null;
    /**
     * @var $signals 信号事件数组
     */
    private $signals = [];
    /**
     * 实例化
     * @param $config 配置信息
     * [
     *     scriptFile=>脚本文件,
     *     pidFile=>进程pid存放文件
     * ]
     */
    public function __construct($config){
        $this->EventBase = new \EventBase();
        if( !isset($config['scriptFile']) ){
            throw new \Exception('scriptFile参数不存在！');
        }
        if( !is_file($config['scriptFile']) ){
            throw new \Exception('scriptFile文件不存在！');
        }
        $this->serverScriptFile = $config['scriptFile'];
        if( !isset($config['pidFile']) ){
            throw new \Exception('pidFile参数不存在！');
        }
        $this->pidFile = $config['pidFile'];
        if( $this->isWindowsOs() ){
            $this->descriptorspec = [];
        }else{
            $this->descriptorspec = array(
                0 => array("pipe", "r"), // 标准输入，子进程从此管道中读取数据
                1 => array("file", "/dev/null",'w'), // 标准输出，子进程向此管道中写入数据
                2 => array("file", "/dev/null","w") // 标准错误，写入到一个文件
            );
        }
    }
    /**
     * 安全打印，用于命令行程序，有终端的情况才打印
     */
    static function safe_dump($var){
        if( !function_exists('posix_isatty') || posix_isatty(STDOUT) ) {
            if( is_string($var) ||is_numeric($var) ){
                echo $var .PHP_EOL;
            }elseif(  is_array($var) ){
                var_export($var);
            }else{
                echo (string)$var . PHP_EOL;
            }
        }
    }
    /**
     * 实现定时器
     * @param callable $callback 可回调
     * @param int $interval 时间间隔，单位秒
     * @return string 定时器的key
     */
    public function timer(callable $callback,$interval){
        if( empty($this->EventBase) ){
            $this->EventBase = new \EventBase();
        }
        $key = uniqid();
        $this->TimerEvents[$key] = \Event::timer($this->EventBase, function()use($callback,$interval,$key){
            $callback();
            $this->TimerEvents[$key]->addTimer($interval);
        });
        $this->TimerEvents[$key]->addTimer($interval);
        return $key;
    }
    /**
     * 添加信号事件
     * @param int $signal 信号
     * @param callable $callback 回调函数
     */
    public function signal($signal,callable $callback){
        $this->signals[$signal] = \Event::signal($this->EventBase,$signal,function($no, $c)use($callback){
            $callback($no, $c);
        });
        $this->signals[$signal]->addSignal();
    }
    /**
     * static::setOsUser()
     * 设置进程的系统用户
     * @return
     */
    static function setOsUser($osUser){
        if( self::isWindowsOs() ){
            return;
        }
        $user_info = posix_getpwnam($osUser);
        if (!$user_info) {
            self::safe_dump( "Warning: User {$osUser} not exsits" );
            return;
        }
        $uid = $user_info['uid'];
        $gid = $user_info['gid'];
    
        if ($uid != posix_getuid() || $gid != posix_getgid()) {
            if (!posix_setgid($gid) || !posix_initgroups($user_info['name'], $gid) || !posix_setuid($uid)) {
                self::safe_dump('Warning: change gid or uid fail.');
            }
        }
    }
    /**
     * 是否是windows系统
     */
    static function isWindowsOs(){
        return strtoupper(substr(PHP_OS, 0, 3)) == 'WIN';
    }
    /**
     * 启动程序
     * $argv[0] 脚本文件
     * $argv[1] 命令；start | restart | stop | daemon
     */
    public function start(){
        global $argv;
        if( !preg_match("/cli/i", php_sapi_name()) ){
            echo '请在命令行启动' . PHP_EOL;
            exit;
        }
        //兼容windows
        if( !self::isWindowsOs() ){
            $this->signal(SIGTERM,function($no, $c){
                $this->stopServer();
                unlink($this->pidFile);
                exit;
            });
            $this->signal(SIGUSR1,function($no, $c){
                $this->stopServer();
                unlink($this->pidFile);
                exit;
            });
            $this->signal(SIGUSR2,function($no, $c){
                $this->stopServer();
                $this->startServer();
            });
        }
        

        if( !isset($argv[1]) || (isset($argv[1]) && $argv[1] == 'start') ){
            self::safe_dump( "启动server" );
            $this->process = proc_open('php ' . $this->serverScriptFile,[], $pipes, NULL , NULL);
            file_put_contents($this->pidFile,getmypid());
            $this->timer(function(){
                $status = proc_get_status($this->process);
                if( $status['running'] == false ){
                    proc_close($this->process);
                    exit;
                }
            },1);
            $this->EventBase->loop();
        }elseif( $argv[1] == 'daemon' ){
            $this->timer(function(){
                $status = proc_get_status($this->process);
                if( $status['running'] == false ){
                    proc_close($this->process);
                    $this->startServer();
                }
            },5);
            cli_set_process_title("php online chat server daemon");
            if( self::isWindowsOs() ){ // windows环境
                $this->startServer();
            }else{ //linux环境
                if( isset($argv[2]) && $argv[2] == 'posix_setsid' ){
                    fclose(STDIN);
                    posix_setsid();
                    $this->startServer();
                }else{
                    $process = proc_open('php ' . $_SERVER['PWD'] . '/' .$argv[0] . ' daemon posix_setsid',[], $pipes, NULL , NULL);
                    exit;
                }
            }
            $this->EventBase->loop();
        }elseif( $argv[1] == 'stop' ){
            if( isset($this->pidFile) ){
                $pid = file_get_contents($this->pidFile);
            }
            posix_kill($pid,SIGUSR1);
        }elseif( $argv[1] == 'restart' ){
            if( isset($this->pidFile) ){
                $pid = file_get_contents($this->pidFile);
            }
            posix_kill($pid,SIGUSR2);
        }
    }
    /**
     * 启动server
     */
    private function startServer(){
        file_put_contents($this->pidFile,getmypid());
        $this->process = proc_open('php ' . $this->serverScriptFile,$this->descriptorspec, $pipes, NULL , NULL);
        $this->pipes = $pipes;
    }
    /**
     * 停止server
     */
    private function stopServer(){
        $status = proc_get_status($this->process);
        if( $status['running'] == true ){
            posix_kill($status['pid'],SIGUSR1);
        }
        proc_close($this->process);
    }
}