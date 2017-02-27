<?php

class App extends \Bingo\Module {
    function __construct() {
        parent::__construct();
        
        \Bingo\Config::loadFile('config',APP_DIR."/config.php");
        
        $this->addModelPath(__DIR__."/App/Models");

        $this->connect("/", ['controller' => 'App\Controllers\Entries', 'action' => 'entries']);
        $this->connect("entries", ['controller' => 'App\Controllers\Entries', 'action' => 'entries']);
        $this->connect(":action", ['controller' => 'App\Controllers\Auth'],
            ['action'=>'(login|logout)']
        );
        $this->connect('attachments/:entry_id/:filename/bitrates.m3u8', ['controller' => 'App\Controllers\Attachments', 'entry_id' => false, 'filename' => false, 'action' => 'video-bitrate-list']);
        $this->connect('attachments/:action/:entry_id/:filename', ['controller' => 'App\Controllers\Attachments', 'entry_id' => false, 'filename' => false],
            ['action'=>'(upload|download|show-original-image|show-video-thumb|play-video|play-audio)']
        );
        $this->connect('attachments/show-attachment-preview/*any', ['controller' => 'App\Controllers\Attachments', 'action' => 'show-resized-image']);
        $this->connect('attachments/show-resized-image/*any', ['controller' => 'App\Controllers\Attachments', 'action' => 'show-resized-image']);
        
        $this->connect('entry_attachments/:entry_id/video_thumbs/:video_name', ['controller' => 'App\Controllers\Attachments', 'action' => 'show-video-thumb']);
        $this->connect('video-part-list/*any', ['controller' => 'App\Controllers\Attachments', 'action' => 'video-part-list']);
        $this->connect('play-video/*any', ['controller' => 'App\Controllers\Attachments', 'action' => 'play-video']);
        
        $this->connect('admin/app/:action/:id', ['controller' => 'App\Controllers\Admin\Users', 'id' => false],
            ['action'=>'(user-list|user-edit)']
        );
        
        $this->connect("developer/:action", ['controller' => 'App\Controllers\Developer']);
        $this->connect("sync-entries", ['controller' => 'App\Controllers\Cron', 'action' => 'sync-entries']);
        
        \Bingo\Action::add('admin_pre_header',
        function () {
            \Admin::$menu[_t('Дневник')][_t('Пользователи')] = 'admin/app/user-list';
        }, $priority = 1);
       
    }
}