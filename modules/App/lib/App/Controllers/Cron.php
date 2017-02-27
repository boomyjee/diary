<?php

namespace App\Controllers;

class Cron extends \Bingo\Controller {
    
    public function __construct() {
        $lock_dir = APP_DIR."/cache/cron_locks";
        if (!file_exists($lock_dir)) mkdir($lock_dir);

        $locked_file = @fopen($lock_dir."/".\Bingo\Routing::$route['action'],"w+");

        if (!$locked_file) { echo "can't open file"; die(); }
        if (!flock($locked_file, LOCK_EX | LOCK_NB)) { echo "cron is busy"; die(); }

        register_shutdown_function(function () use ($locked_file){
            flock($locked_file, LOCK_UN);
            fclose($locked_file);
        });
    }
    
    public function sync_entries() {       
        $config = \Bingo\Config::get('config', 'cloud_mailru');
        $cloudAPI = new \CloudMailruAPI($config['login'], $config['password']);
        
        $removeDir = function($path) use(&$removeDir) {
            $files = glob($path.'/*');
            foreach ($files as $file) {
                is_dir($file) ? $removeDir($file) : @unlink($file);
            }
            @rmdir($path);
        };
        
        $entries = \App\Models\Entry::findBy(['synced' => false]);
        foreach ($entries as $entry) {
            $cloudAttachmentsDir = \App\Models\Entry::CLOUD_STORAGE_BASE_FOLDER.'/'.$entry->id;
            $localAttachmentsDir = INDEX_DIR.'/entry_attachments/'.$entry->id;
            $tmpFilesDir = INDEX_DIR.'/cache/tmp_files';
            $synced = true;
            
            if (!$entry->deleted) {
                $filename = uniqid($entry->id.'_').".txt";
                file_put_contents($tmpFilesDir.'/'.$filename, $entry->text);
                $res = $cloudAPI->loadFile($tmpFilesDir.'/'.$filename, \App\Models\Entry::CLOUD_STORAGE_TEXT_FILENAME, $cloudAttachmentsDir);
                if ($res === false) $synced = false;
                unlink($tmpFilesDir.'/'.$filename);

                $cloudFiles = $cloudAPI->getFiles($cloudAttachmentsDir);
                if ($cloudFiles === false) continue;

                $existedAttachments = [];
                foreach ($cloudFiles as $file) {
                    if ($file['name'] == \App\Models\Entry::CLOUD_STORAGE_TEXT_FILENAME) continue;
                    if (!in_array($file['name'], $entry->attachments)) {
                        $res = $cloudAPI->removeFile($cloudAttachmentsDir.'/'.$file['name']);
                        if ($res !== false) @unlink($localAttachmentsDir.'/'.$file['name']);
                        else $synced = false;
                    } else { 
                        $existedAttachments[] = $file['name'];
                    }
                }

                foreach ($entry->attachments as $attachment) {
                    if (in_array($attachment, $existedAttachments) && !file_exists($tmpFilesDir.'/'.$attachment)) continue;
                    if (!file_exists($tmpFilesDir.'/'.$attachment)) {
                        trigger_error('Attachment '.$attachment.' was not found in temporary folder');
                        continue;
                    }
                    
                    $res = $cloudAPI->loadFile($tmpFilesDir.'/'.$attachment, $attachment, $cloudAttachmentsDir);
                    if ($res !== false) unlink($tmpFilesDir.'/'.$attachment);
                    else $synced = false; 
                }
                
                if ($synced) {
                    $entry->synced = true;
                    $entry->save();
                }
            } else {
                $res = $cloudAPI->removeFile($cloudAttachmentsDir);
                if ($res !== false) {
                    $entry->delete();
                    $removeDir($localAttachmentsDir);
                }
            }
        }
    }
}