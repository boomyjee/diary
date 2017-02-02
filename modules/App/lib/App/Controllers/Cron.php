<?php

namespace App\Controllers;

class Cron extends \Bingo\Controller {
    
    public function __construct() {
        $lock_dir = INDEX_DIR."/cache/cron_locks";
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
            $tmpFilesDir = INDEX_DIR.'/tmp_files';
            $synced = true;
            
            if (!$entry->deleted) {
                $filename = uniqid($entry->id.'_').".txt";
                file_put_contents($tmpFilesDir.'/'.$filename, $entry->text);
                try {
                    $cloudAPI->loadFile($tmpFilesDir.'/'.$filename, \App\Models\Entry::CLOUD_STORAGE_TEXT_FILENAME, $cloudAttachmentsDir);
                } catch (\Exception $e) {
                    $synced = false;
                }
                unlink($tmpFilesDir.'/'.$filename);

                $cloudFiles = [];
                try {
                    $cloudFiles = $cloudAPI->getFiles($cloudAttachmentsDir);
                } catch (\Exception $e) {
                    $synced = false;
                }

                $existedAttachments = [];
                foreach ($cloudFiles as $file) {
                    if ($file['name'] == \App\Models\Entry::CLOUD_STORAGE_TEXT_FILENAME) continue;
                    if (!in_array($file['name'], $entry->attachments)) {
                        try {
                            $cloudAPI->removeFile($cloudAttachmentsDir.'/'.$file['name']);
                             @unlink($localAttachmentsDir.'/'.$file['name']);
                        } catch (\Exception $e) {
                            $synced = false;
                        }
                    } else { 
                        $existedAttachments[] = $file['name'];
                    }
                }

                foreach ($entry->attachments as $attachment) {
                    if (in_array($attachment, $existedAttachments)) continue;
                    if (!file_exists($tmpFilesDir.'/'.$attachment)) {
                        trigger_error('Attachment '.$attachment.' was not found in temporary folder');
                        continue;
                    }
                    
                    try {
                        $cloudAPI->loadFile($tmpFilesDir.'/'.$attachment, $attachment, $cloudAttachmentsDir);
                        unlink($tmpFilesDir.'/'.$attachment);
                    } catch (\Exception $e) {
                        $synced = false;
                    }
                }
                
                if ($synced) {
                    $entry->synced = true;
                    $entry->save();
                }
            } else {
                try {
                    $cloudAPI->removeFile($cloudAttachmentsDir);
                    $removeDir($localAttachmentsDir);
                } catch (\Exception $e) {
                    $synced = false;
                }
                
                if ($synced) $entry->delete();
            }
        }
    }
}