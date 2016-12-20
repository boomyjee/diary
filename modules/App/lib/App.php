<?php

class App extends \Bingo\Module {
    function __construct() {
        parent::__construct();

        require __DIR__.'/Guzzle/vendor/autoload.php';
        
        \Bingo\Config::loadFile('config',INDEX_DIR."/config.php");
        
        $this->addModelPath(__DIR__."/App/Models");

        $this->connect("/", ['controller' => 'App\Controllers\Entries', 'action' => 'entries']);
        $this->connect("entries", ['controller' => 'App\Controllers\Entries', 'action' => 'entries']);
        $this->connect(":action", ['controller' => 'App\Controllers\Auth'],
            ['action'=>'(login|logout)']
        );
        $this->connect('attachments/:entry_id/:filename/bitrates.m3u8', ['controller' => 'App\Controllers\Attachments', 'entry_id' => false, 'filename' => false, 'action' => 'video-bitrate-list']);
        $this->connect('attachments/:action/:entry_id/:filename', ['controller' => 'App\Controllers\Attachments', 'entry_id' => false, 'filename' => false],
            ['action'=>'(upload|download|show-resized-image|show-original-image|show-video-thumb|play-video|play-audio)']
        );
        $this->connect('attachments/show-preview/:sub_dir/:filename', ['controller' => 'App\Controllers\Attachments', 'base_dir' => 'entry_attachments', 'filename' => false, 'action' => 'show-attachment-preview']);
        $this->connect('attachments/show-preview/:filename', ['controller' => 'App\Controllers\Attachments','base_dir' => 'tmp_files', 'sub_dir' => false, 'filename' => false, 'action' => 'show-attachment-preview']);
        
        $this->connect('video-part-list/*any', ['controller' => 'App\Controllers\Attachments', 'action' => 'video-part-list']);
        $this->connect('play-video/*any', ['controller' => 'App\Controllers\Attachments', 'action' => 'play-video']);
        
        $this->connect('admin/app/:action/:id', ['controller' => 'App\Controllers\Admin\Users', 'id' => false],
            ['action'=>'(user-list|user-edit)']
        );
        
        $this->connect("sync-entries", ['controller' => 'App\Controllers\Cron', 'action' => 'sync-entries']);
        
        \Bingo\Action::add('admin_pre_header',
        function () {
            \Admin::$menu[_t('Дневник')][_t('Пользователи')] = 'admin/app/user-list';
        }, $priority = 1);
       
    }
}