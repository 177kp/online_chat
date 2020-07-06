<?php
namespace app\online_chat\controller;
use think\Db;
use think\Controller;
use think\facade\Request;
use onlineChat\model\Message;

class File extends Controller{
    /**
     * $_FILE[file] 上传文件
     * 上传根目录是$_SERVER[DOCUMENT_ROOT]/upload；下载地址是:$domain/upload/*
     * 文件大小限制：10M
     * 音频文件的mime_type限制：audio/wav,audio/x-m4a
     * 图片文件名称后缀限制：jpg,jpeg,png,gif,bmp,ico；mime_type；限制：image/*
     * 视频文件后缀限制：mp4
     * 其他文件：无文件名后缀和mime_type限制；但文件保存的是没有后缀的
     * 视频、音频需要ffmpeg支持；视频需要获取封面图片、时长；音频需要获取到时长
     */
    public function upload(){
        isLogin();
        $upload_root_dir = $_SERVER['DOCUMENT_ROOT'] . '/upload';
        $file = request()->file('file');
        //var_dump( $file->getMime() );exit;
        if( in_array( $file->getInfo('type'),['audio/wav','audio/x-m4a']) ){
            $msg_type = Message::MSG_TYPE_SOUND;
            $upload_dir = $upload_root_dir . '/sound/';
            $is_mp3 = 0;
        }elseif( $file->checkExt(['jpg','jpeg','png','gif','bmp','ico']) || preg_match('/^image\/.+/',$file->getMime()) ){
            $msg_type = Message::MSG_TYPE_IMG;
            $upload_dir = $upload_root_dir . '/img/';
        }elseif( $file->checkExt(['mp3']) ){
            $msg_type = Message::MSG_TYPE_SOUND;
            $upload_dir = $upload_root_dir . '/sound/';
            $is_mp3 = 1;
        }elseif( $file->checkExt(['mp4']) ){
            $msg_type = Message::MSG_TYPE_VIDEO;
            $upload_dir = $upload_root_dir . '/video/';
        }else{
            $msg_type = Message::MSG_TYPE_FILE;
            $upload_dir = $upload_root_dir . '/file/';
        }
        if( !$file->checkSize(1024*1024*10) ){
            returnMsg(100,'上传文件大小不能超过10M！');
        }
        $filesize = $file->getSize();
        if( $msg_type == Message::MSG_TYPE_FILE ){
            $info = $file->move( $upload_dir,true,true,false );
        }elseif( $msg_type == Message::MSG_TYPE_SOUND && $is_mp3 == 0 ){
            $info = $file->move( $upload_dir,true,true,false );
        }else{
            $info = $file->move( $upload_dir );
        }
        
        if( !$info ){
            returnMsg(100,'上传文件失败！');
        }
        $path = $upload_dir . $info->getSaveName();
        if( $msg_type == Message::MSG_TYPE_SOUND || $msg_type == Message::MSG_TYPE_VIDEO ){
            //echo 'ffmpeg -i ' . $path . ' -f null -';
            $duration = $this->ffmpeg_get_mp3_duration($path);
            if( $duration == false ){
                $duration = 0;
                $msg = '获取时长失败！请检查是否安装了ffmpeg!';
            }
        }else{
            $duration = 0;
        }

        if( $msg_type == Message::MSG_TYPE_VIDEO ){
            $video_cover_img = $this->getVideoCover($path,1,200,150);
            $video_cover_img = str_replace($_SERVER['DOCUMENT_ROOT'],'',$video_cover_img);
            $video_cover_img = str_replace('\\','/',$video_cover_img);
        }else{
            $video_cover_img = '';
        }
        
        $filename = $file->getInfo('name');
        unset($file,$info);
        if( $msg_type == Message::MSG_TYPE_IMG ){
            $image = \think\Image::open($path);
            $size = $image->size();
            if( $size[0] > 750 ){
                $image->thumb(750,750,\think\Image::THUMB_SCALING)->save($path);
            }
        }
        if( $msg_type == Message::MSG_TYPE_SOUND && $is_mp3 == 0 ){
            $path = $this->toMp3($path);
        }
        $path = str_replace($_SERVER['DOCUMENT_ROOT'],'',$path);
        $path = str_replace('\\','/',$path);

        //var_dump(Request::domain());
        //文件的host
        $fileHost=  Request::domain();
        returnMsg(200,'上传成功！',[
            'msg_type'=>$msg_type,
            'filename'=>$filename,
            'path'=>$fileHost . $path,
            'duration'=>$duration,
            'video_cover_img'=>$video_cover_img != '' ? $fileHost . $video_cover_img : '',
            'filesize'=>$filesize
        ]);
    }
    /**
     * 文件下载
     * $_GET[path] 文件路径，必须是/upload/file/*
     * $_GET[filename] 源文件路径
     */
    public function download(){
        session_write_close();
        if( !isset($_GET['path']) ){
            returnMsg(100,'path不存在！');
        }
        if( !isset($_GET['filename']) ){
            returnMsg(100,'filename不存在！');
        }
        if( empty($_GET['path']) ){
            returnMsg(100,'path不能为空！');
        }
        if( strstr($_GET['path'],'..') !== false ){
            returnMsg(100,'path不正确！');
        }
        $_GET['path'] = str_replace(Request::domain(),'',$_GET['path']);
        if( !preg_match('/^\/upload\/file\//',$_GET['path']) ){
            returnMsg(100,'path不正确！');
        }
        
        $path = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['path'];
        $path = str_replace('//','/',$path);
        if( !is_file($path) ){
            returnMsg(100,'path不正确！');
        }
        //以只读和二进制模式打开文件   
        $file = fopen ( $path, "rb" ); 
        //告诉浏览器这是一个文件流格式的文件    
        Header ( "Content-type: application/octet-stream" ); 
        //请求范围的度量单位  
        Header ( "Accept-Ranges: bytes" );  
        //Content-Length是指定包含于请求或响应中数据的字节长度    
        Header ( "Accept-Length: " . filesize ( $path ) );  
        //用来告诉浏览器，文件是可以当做附件被下载，下载后的文件名称为$file_name该变量的值。
        Header ( "Content-Disposition: attachment; filename=" . $_GET['filename'] );    
        //读取文件内容并直接输出到浏览器    
        echo fread ( $file, filesize ( $path ) );    
        fclose ( $file );  
        exit;//需要exit，要不然thinkphp会改变content-type
    }
    /**
     * 获取音频和视频的时长
     * @param $path 路径
     * @return string 时长
     */
    protected function ffmpeg_get_mp3_duration($path){
        //echo 'ffmpeg -i '.$path.' -f null - 2>&1';exit;
        ob_start();
        system('ffmpeg -i '.$path.' -f null - 2>&1' );
        $res = ob_get_contents();
        ob_clean();
        //var_dump($res);exit;
        if( !preg_match('/Duration:([^,]+)/',$res,$match) ){
            return false;
        }
        $arr = explode(':',$match[1]);
        $second = 0;
        isset($arr[0]) && $second+= 3600*$arr[0];
        isset($arr[1]) && $second+= 60*$arr[1];
        isset($arr[2]) && $second+= $arr[2];
        isset($arr[3]) && $second+= round( $arr[3] / 100 , 2);
        if( $second > 1 ){
            $second = (int)$second;
        }else{
            $second = round($second,2);
        }
        return (string)$second;
    }
    /**
     * 获取视频封面图片
     * @param string $inFile 视频文件地址
     * @param int $time 截图的秒数
     * @param int $width 图片宽度
     * @param int $height 图片高度
     */
    protected function getVideoCover($inFile, $time = 1, $width = 320, $height = 240)
    {
        //输出文件名
        $outFile = $inFile . '.cover.jpg';
        //ffmpeg文件路径
        $ffmpeg = 'ffmpeg';
        //运行命令
        $command = $ffmpeg . " -i " . $inFile . " -y -f image2 -t {$time} -s {$width}x{$height} " . $outFile;
        $res = system($command);
        if( !is_file($outFile) ){
            return APP_ROOT_DIR . '/public/static/img/video-cover.jpg';
        }else{
            return $outFile;
        }
        
    }
    /**
     * 音频文件转成mp3
     * @param $path 路径
     * @return string 路径
     */
    protected function toMp3($path){
        $command = 'ffmpeg -i '.$path.' -ab 16 -ar 16000 '.$path.'.mp3';
        system($command);
        unlink($path);
        return $path . '.mp3';
    }
}