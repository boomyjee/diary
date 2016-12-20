<?php

namespace App\Models;

/**
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="app_entries")
 */
class Entry extends \ActiveEntity
{
    /**
     * @Id @Column(type="integer")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;
        
    /** @Column(type="text") */
    public $text;
    
    /** @Column(type="datetime") */
    public $created;
    
    /** @Column(type="array") */
    public $attachments;
    
    /**
     * @ManyToOne(targetEntity="User")
     * @JoinColumn(name="author_id", referencedColumnName="id")
     */
    public $author;
    
    /** @Column(type="array", nullable=true) */
    public $attachment_preview_settings;
    
    const CLOUD_STORAGE_BASE_FOLDER = 'Diary/Entries';
    const CLOUD_STORAGE_TEXT_FILENAME = 'text.txt';
    
    const ATTACHMENT_TYPE_IMAGE = 'image';
    const ATTACHMENT_TYPE_VIDEO = 'video';
    const ATTACHMENT_TYPE_AUDIO = 'audio';
    const ATTACHMENT_TYPE_OTHER = 'other';
    
    function __construct() {
        $this->created = new \DateTime('now');
        $this->attachment_previews_settings = [];
    }
   
    /** @PostPersist @PostUpdate */
    public function postPersist() {
        $config = \Bingo\Config::get('config', 'cloud_mailru');
        $cloudAPI = new \CloudMailruAPI($config['login'], $config['password'], INDEX_DIR.'/cache/tmp_cookies.dat');
        $cloudAttachmentsFolder = self::CLOUD_STORAGE_BASE_FOLDER.'/'.$this->id;
        $localAttachmentsFolder = INDEX_DIR.'/entry_attachments/'.$this->id;
        if (!file_exists($localAttachmentsFolder))
            mkdir($localAttachmentsFolder, 0755, $recursive = true);
        
        $tmpFilesDir = INDEX_DIR.'/tmp_files';
        if (!file_exists($tmpFilesDir)) mkdir($tmpFilesDir, 0755);
        
        preg_match_all("/#([\w|\p{L}]+)/u", $this->text, $matches);
        $oldTags = $this->getTags();
        $newTags = !empty($matches[1]) ? $matches[1] : [];
        
        \Bingo::$em->createQuery("DELETE Meta\Models\Tag t WHERE t.type = :type AND t.owner_id = :owner_id AND t.value IN (:values)")
            ->setParameter('type', get_class($this))
            ->setParameter('owner_id', $this->id)
            ->setParameter('values', array_diff($oldTags, $newTags))
            ->execute()
        ;
        $this->setTags($matches[1]);

        $filename = uniqid($this->id.'_').".txt";
        file_put_contents($tmpFilesDir.'/'.$filename, $this->text);
        try {
            $cloudAPI->loadFile($tmpFilesDir.'/'.$filename, self::CLOUD_STORAGE_TEXT_FILENAME, $cloudAttachmentsFolder);
        } catch (\Exception $e) {
            trigger_error($e->getMessage());
        }
        @unlink($tmpFilesDir.'/'.$filename);

        $existedAttachments = [];
        $cloudFiles = [];
        try {
            $cloudFiles = $cloudAPI->getFiles($cloudAttachmentsFolder);
        } catch (\Exception $e) {
            trigger_error($e->getMessage());
        }
        
        foreach ($cloudFiles as $file) {
            if ($file['name'] == self::CLOUD_STORAGE_TEXT_FILENAME) continue;
            if (!in_array($file['name'], $this->attachments)) {
                try {
                    $cloudAPI->removeFile($cloudAttachmentsFolder.'/'.$file['name']);
                } catch (\Exception $e) {
                    trigger_error($e->getMessage());
                }
                @unlink($localAttachmentsFolder.'/'.$file['name']);
            } else { 
                $existedAttachments[] = $file['name'];
            }
        }

        foreach ($this->attachments as $attachment) {
            if (!file_exists($tmpFilesDir.'/'.$attachment)) continue;
            try {
                $cloudAPI->loadFile($tmpFilesDir.'/'.$attachment, $attachment, $cloudAttachmentsFolder);
            } catch (\Exception $e) {
                trigger_error($e->getMessage());
            }
            
            $attachmentType = self::getAttachmentType($attachment);
            if ($attachmentType == self::ATTACHMENT_TYPE_IMAGE) {
                $attachmentPreviewPath = $tmpFilesDir.'/preview/'.$attachment;
                if (file_exists($attachmentPreviewPath)) {
                    @rename($attachmentPreviewPath, $localAttachmentsFolder.'/preview/'.$attachment);
                    @unlink($attachmentPreviewPath);
                }
                    
                try {
                    $file = \PhpThumb\Factory::create($tmpFilesDir.'/'.$attachment, ['resizeUp' => false]);
                    $file->resize(1280, 950);
                    $file->save($localAttachmentsFolder.'/'.$attachment);
                } catch (\Exception $e) {
                    trigger_error($e->getMessage());
                }
            } else if (in_array($attachmentType, [self::ATTACHMENT_TYPE_OTHER, self::ATTACHMENT_TYPE_AUDIO])) {
                @rename($tmpFilesDir.'/'.$attachment, $localAttachmentsFolder.'/'.$attachment);
            }
            
            @unlink($tmpFilesDir.'/'.$attachment);
        }
    }
    
    /** @preRemove */
    public function preRemove() {
        $config = \Bingo\Config::get('config', 'cloud_mailru');
        $cloudAPI = new \CloudMailruAPI($config['login'], $config['password'], INDEX_DIR.'/cache/tmp_cookies.dat');
        $cloudAttachmentsFolder = self::CLOUD_STORAGE_BASE_FOLDER.'/'.$this->id;
        $localAttachmentsFolder = INDEX_DIR.'/entry_attachments/'.$this->id;
        
        $removeDir = function($path) use(&$removeDir) {
            $files = glob($path.'/*');
            foreach ($files as $file) {
                is_dir($file) ? $removeDir($file) : @unlink($file);
            }
            @rmdir($path);
        };

        try {
            $cloudAPI->removeFile($cloudAttachmentsFolder);
        } catch (\Exception $e) {
            trigger_error($e->getMessage());
        }
        $removeDir($localAttachmentsFolder);
    }
    
    
    public static function getSearchQuery($id = false, $author = null, $text = '', $tags = []) {
        $qb = \Bingo::$em->createQueryBuilder()
            ->select('e')
            ->from('App\Models\Entry', 'e')
            ->orderBy('e.created', 'DESC');
        
        if ($id) {
            $qb->andWhere('e.id = :id');
            $qb->setParameter('id', $id);
        }
        
        if ($author) {
            $qb->andWhere('e.author = :author');
            $qb->setParameter('author', $author);
        }
        
        if ($text) {
            $qb->andWhere("e.text LIKE :text");
            $qb->setParameter('text', '%'.$text.'%');
        }
        
        if (count($tags)) { 
            $qb->andWhere("e.id IN (SELECT t.owner_id FROM \Meta\Models\Tag t WHERE t.type = :tag_type AND t.value IN (:tags))");
            $qb->setParameter('tags', $tags);
            $qb->setParameter('tag_type', static::class);
        }
        
        return $qb->getQuery();
    }
    
    public static function getAttachmentType($attachment) {
        $extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
        if (in_array($extension, ['png', 'jpeg', 'gif', 'jpg'])) {
            return self::ATTACHMENT_TYPE_IMAGE;
        } else if (in_array($extension, ['webm', 'mp4', 'ogv', 'avi', 'asf', 'flv'])) {
            return self::ATTACHMENT_TYPE_VIDEO;
        } else if ($extension == 'mp3') {
            return self::ATTACHMENT_TYPE_AUDIO;
        }
        
        return  self::ATTACHMENT_TYPE_OTHER;
    }
    
    public static function getAttachmentOriginalName($attachment) {
        return mb_substr($attachment, 14, null, 'UTF-8');
    }
    
    public function getTags() {
        return \Meta\Models\Tag::getTags($this); 
    }
    
    public function setTags($tags) {
        return \Meta\Models\Tag::setTags($this, $tags); 
    }
}