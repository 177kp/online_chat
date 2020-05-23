<?php
namespace onlineChat\lib;
use Exception;
use think\exception\Handle;
use think\console\Output as ConsoleOutput;
use think\facade\Log;
/**
 * 异常处理
 */
class ExceptionHandle extends Handle
{
    /**
     * 渲染异常
     * @param Exception $e
     */
    public function render(Exception $e)
    {
        if( !function_exists('posix_isatty') || posix_isatty(STDOUT) ) {
            echo (string)$e . PHP_EOL;
        }
        return parent::render($e);
    }
    /**
     * 命令行模式下的渲染异常
     * @param ConsoleOutput $ConsoleOutput
     * @param Exception $e 
     */
    public function renderForConsole(ConsoleOutput $ConsoleOutput,Exception $e){
        if( !function_exists('posix_isatty') || posix_isatty(STDOUT) ) {
            echo (string)$e . PHP_EOL;
        }
        return parent::render($e);
    }
    /**
     * 聊天的渲染异常
     * @param Exception $e
     */
    static function chatRenderException( $e ){
        if( !function_exists('posix_isatty') || posix_isatty(STDOUT) ) {
            echo (string)$e . PHP_EOL;
        }
        Log::error((string)$e);
    }
}