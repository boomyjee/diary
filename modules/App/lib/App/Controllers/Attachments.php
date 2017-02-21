<?php

namespace App\Controllers;

use \App\Models\Entry;

class Attachments extends Base {
    
    public function __construct() {
        parent::__construct();
        $config = \Bingo\Config::get('config', 'cloud_mailru');
        $this->cloudAPI = new \CloudMailruAPI($config['login'], $config['password']);
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
        header('Cache-Control: max-age=3600');
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
    
    public function upload() {
        $tmpFilesDir = INDEX_DIR.'/cache/tmp_files';
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
            $fileinfo = pathinfo($_FILES['attachment']['name']);
            $filename = mb_substr($fileinfo['filename'], 0, 100, 'UTF-8').'.'.$fileinfo['extension'];
            $counter = 1;
            if (file_exists($tmpFilesDir.'/'.$filename)) {
                $basename = pathinfo($filename, PATHINFO_FILENAME);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                while (file_exists($tmpFilesDir.'/'.$basename.'('.$counter.').'.$extension)) {
                    $counter++;
                }
                $filename = $basename.'('.$counter.').'.$extension;
            }
            move_uploaded_file($_FILES['attachment']['tmp_name'], $tmpFilesDir.'/'.$filename);
                
            $resizeAttachedImage = function($imagePath, $destinationPath, $width, $height) {
                try {
                    $file = \PhpThumb\Factory::create($imagePath, ['resizeUp' => false]);
                    $file->resize($width, $height);
                    $file->save($destinationPath);
                } catch (\Exception $e) {
                    trigger_error($e->getMessage());
                }
            };

            $previewUrl = '';
            $attachmentType = Entry::getAttachmentType($filename);
            if ($attachmentType == Entry::ATTACHMENT_TYPE_IMAGE) {
                $resizeAttachedImage($tmpFilesDir.'/'.$filename, $tmpImgsPreviewDir.'/'.$filename, 500, 500); //generate preview
                $resizeAttachedImage($tmpFilesDir.'/'.$filename, $tmpResizedImgsDir.'/'.$filename, 1920, 1200); //generate resized copy
                $previewUrl = url('cache/tmp_files/images_preview/'.rawurlencode($filename));
            } elseif ($attachmentType == Entry::ATTACHMENT_TYPE_VIDEO) {
                $previewUrl = url('assets/images/video-thumb.jpg');
            }
            
            echo json_encode(['status' => 'success', 'filename' => $filename, 'preview_url' => $previewUrl]);
        }
    }
}