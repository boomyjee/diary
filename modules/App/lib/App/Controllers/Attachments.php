<?php

namespace App\Controllers;

use \App\Models\Entry;

class Attachments extends BasePrivate {
    
    public function __construct() {
        parent::__construct();
        $config = \Bingo\Config::get('config', 'cloud_mailru');
        $this->cloudAPI = new \CloudMailruAPI($config['login'], $config['password']);
    }
    
    public function show_resized_image($path) {
        $previewPath = INDEX_DIR.'/'.$path;
        if (!file_exists($previewPath)) return;
        
        $previewInfo = getimagesize($previewPath);
        $mimeInfo = isset($previewInfo['mime']) ? $previewInfo['mime'] : null;

        header("Content-type: $mimeInfo");
        readfile($previewPath);
    }
    
    public function show_original_image($entryId, $filename) {
        if (!$entryId || !$filename) return;
        $imagePath = Entry::CLOUD_STORAGE_BASE_FOLDER.'/'.$entryId.'/'.$filename;
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        
        switch($ext) {
            case "gif": $ctype="image/gif"; break;
            case "png": $ctype="image/png"; break;
            case "jpeg":
            case "jpg": $ctype="image/jpeg"; break;
            default:
        }
        
        header('Content-type: '.$ctype);        
        try {
            echo $this->cloudAPI->getFileContent($imagePath);
        } catch (\Exception $e) {
            trigger_error($e->getMessage());
        }
    }
    
    public function show_video_thumb($entryId, $videoName) {
        if (!$entryId || !$videoName) return; 
        $videoThumbsDir = INDEX_DIR.'/entry_attachments/'.$entryId.'/video_thumbs';
        $videoThumbsPath = $videoThumbsDir.'/'.str_replace('.', '_', $videoName).'.jpg';
        
        if (!is_dir($videoThumbsDir)) 
            mkdir($videoThumbsDir, $mode = 0755, $recursive = true);
        
        if (!file_exists($videoThumbsPath)) {
            try {
                $file = fopen($videoThumbsPath, "w");
                fwrite($file, $this->cloudAPI->getVideoThumb(Entry::CLOUD_STORAGE_BASE_FOLDER.'/'.$entryId.'/'.$videoName));
                fclose($file);
            } catch (\Exception $e) {
                // can't get video thumb or thumb isn't uploaded to storage yet
                unlink($videoThumbsPath);
            }
        }
        
        if (!file_exists($videoThumbsPath))
            $videoThumbsPath = INDEX_DIR . '/assets/images/video-thumb.jpg';
            
        $thumbInfo = getimagesize($videoThumbsPath);
        $mimeInfo = isset($thumbInfo['mime']) ? $thumbInfo['mime'] : null;
        header("Content-type: $mimeInfo");
        readfile($videoThumbsPath);
    }
    
    public function video_bitrate_list($entryId, $videoName) {
        if (!$entryId || !$videoName) return; 
        header("Content-type: application/x-mpegURL");
        try {
            $list = $this->cloudAPI->getVideoBitrates(Entry::CLOUD_STORAGE_BASE_FOLDER.'/'.$entryId.'/'.$videoName);
        } catch (\Exception $e) {
            // can't get video bitrate list or video isn't uploaded to storage yet
            return;
        }
        echo preg_replace('/\/media\//', 'https://'.$_SERVER['HTTP_HOST'].url('/video-part-list/media/'), $list);
    }
    
    public function video_part_list($videoUrl) {
        header("Content-type: application/x-mpegURL");
        try {
            $playList = $this->cloudAPI->getVideo($videoUrl.'?double_encode=1');
        } catch (\Exception $e) {
            // can't get video part list or video isn't uploaded to storage yet
            return;
        }
        echo preg_replace('/\/media\//', 'https://'.$_SERVER['HTTP_HOST'].url('/play-video/media/'), $playList);
    }
    
    public function play_video($videoUrl) {
        $tryCount = 2;
        while ($tryCount--) {
            try {
                echo $this->cloudAPI->getVideo($videoUrl.'?double_encode=1');
                break;
            } catch (\Exception $e) {
                if ($tryCount == 0)
                    trigger_error($e->getMessage());
            }
        }
    }
    
    public function play_audio($entryId, $audioName) {
        $file = INDEX_DIR.'/entry_attachments/'.$entryId.'/'.$audioName;
        $fp = @fopen($file, 'rb');
        $size = filesize($file); 
        $length = $size;
        $start = 0;
        $end = $size - 1;
        header('Content-type: audio/mpeg');
        header("Accept-Ranges: bytes");
        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $start;
            $c_end = $end;
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }

            if ($range == '-') {
                $c_start = $size - substr($range, 1);
            }else{
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
            }
            $c_end = ($c_end > $end) ? $end : $c_end;

            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }
            $start = $c_start;
            $end = $c_end;
            $length = $end - $start + 1;
            fseek($fp, $start);
            header('HTTP/1.1 206 Partial Content');
        }
        header("Content-Range: bytes $start-$end/$size");
        header("Content-Length: ".$length);
        $buffer = 1024 * 8;
        while(!feof($fp) && ($p = ftell($fp)) <= $end) {
            if ($p + $buffer > $end) {
                $buffer = $end - $p + 1;
            }
            set_time_limit(0);
            echo fread($fp, $buffer);
            flush();
        }
        fclose($fp);
    }
    
    public function upload() {
        $tmpFilesDir = INDEX_DIR.'/tmp_files';
        $tmpImgsPreviewDir = $tmpFilesDir.'/images_preview';
        $tmpResizedImgsDir = $tmpFilesDir.'/resized_images';
        
        $removeExpiredFiles = function($dir) {
            $tmpFiles = array_diff(scandir($dir), ['.', '..']);
            foreach ($tmpFiles as $filename) {
                $filetime = @filemtime($dir."/".$filename);
                if ($filetime < time() - 86400)
                    @unlink($dir."/".$filename);
            }
        };
        
        foreach ([$tmpFilesDir, $tmpImgsPreviewDir, $tmpResizedImgsDir] as $dir) {
            if (!file_exists($dir)) mkdir($dir, 0755);
            $removeExpiredFiles($dir);
        }
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $filename = uniqid().'_'.$_FILES['attachment']['name'];
            $fileinfo = pathinfo($filename);
            $filename = mb_substr($fileinfo['filename'], 0, 100, 'UTF-8').'.'.$fileinfo['extension'];
            move_uploaded_file($_FILES['attachment']['tmp_name'], $tmpFilesDir.'/'.$filename);
                
            $previewUrl = '';
            $attachmentType = Entry::getAttachmentType($filename);
            if ($attachmentType == Entry::ATTACHMENT_TYPE_IMAGE) {
                \App\Models\Entry::resizeAttachedImage($tmpFilesDir.'/'.$filename, $tmpImgsPreviewDir.'/'.$filename, 500, 500); //generate preview
                \App\Models\Entry::resizeAttachedImage($tmpFilesDir.'/'.$filename, $tmpResizedImgsDir.'/'.$filename, 1280, 950); //generate resized copy
                $previewUrl = url('attachments/show-attachment-preview/tmp_files/images_preview/'.rawurlencode($filename));
            } elseif ($attachmentType == Entry::ATTACHMENT_TYPE_VIDEO) {
                $previewUrl = url('assets/images/video-thumb.jpg');
            }
            
            echo json_encode(['status' => 'success', 'original_filename' => Entry::getAttachmentOriginalName($filename), 'filename' => $filename, 'preview_url' => $previewUrl]);
        }
    }
    
    public function download($entryId, $filename) {
        if (!$entryId || !$filename) return;        
        $cloudFilePath = Entry::CLOUD_STORAGE_BASE_FOLDER.'/'.$entryId.'/'.$filename;
        $localFilePath = INDEX_DIR.'/entry_attachments/'.$entryId.'/'.$filename;
        
        header('Content-Disposition: attachment; filename="' . Entry::getAttachmentOriginalName($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        
        if (file_exists($localFilePath)) {
            readfile($localFilePath);
        } else {
            try {
                echo $this->cloudAPI->getFileContent($cloudFilePath);
            } catch (\Exception $e) {
                trigger_error($e->getMessage());
            }
        }
    }
}