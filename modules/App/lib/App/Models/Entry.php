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
    
    /** @Column(type="boolean") */
    public $synced;
    
    /** @Column(type="boolean") */
    public $deleted;
    
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
        $this->text = '';
        $this->synced = false;
        $this->deleted = false;
        $this->attachments = [];
        $this->attachment_previews_settings = [];
    }
   
    /** @PreUpdate */
    public function preUpdate(\Doctrine\ORM\Event\PreUpdateEventArgs $event) {        
        if ($event->hasChangedField('attachments') || $event->hasChangedField('text') || $event->hasChangedField('deleted')) 
            $this->synced = false;
    }
    
    /** @PostPersist @PostUpdate */
    function postPersist() {
        preg_match_all("/#([\w|\p{L}]+)/u", $this->text, $matches);
        $oldTags = $this->getTags();
        $newTags = !empty($matches[1]) ? $matches[1] : [];

        \Bingo::$em->createQuery("DELETE Meta\Models\Tag t WHERE t.type = :type AND t.owner_id = :owner_id AND t.value IN (:values)")
            ->setParameter('type', get_class($this))
            ->setParameter('owner_id', $this->id)
            ->setParameter('values', array_diff($oldTags, $newTags))
            ->execute()
        ;

        $this->setTags($newTags);
        
        $tmpFilesDir = INDEX_DIR.'/tmp_files';
        $attachmentsDir = INDEX_DIR.'/entry_attachments/'.$this->id;
        
        $directories = [
            $tmpFilesDir,
            $attachmentsDir,
            $attachmentsDir.'/images_preview',
            $attachmentsDir.'/resized_images'
        ];
        
        foreach ($directories as $dir) 
            if (!file_exists($dir)) mkdir($dir, 0755, true);

        foreach ($this->attachments as $attachment) {
            if (file_exists($attachmentsDir.'/'.$attachment)) continue;
            if (!file_exists($tmpFilesDir.'/'.$attachment)) continue;
            
            $attachmentType = self::getAttachmentType($attachment);
            if ($attachmentType == self::ATTACHMENT_TYPE_IMAGE) {                
                foreach (['images_preview', 'resized_images'] as $subDir) {
                    if (file_exists($attachmentsDir.'/'.$subDir.'/'.$attachment)) continue;
                    if (file_exists($tmpFilesDir.'/'.$subDir))
                        rename($tmpFilesDir.'/'.$subDir.'/'.$attachment, $attachmentsDir.'/'.$subDir.'/'.$attachment);
                }
            } else if (in_array($attachmentType, [self::ATTACHMENT_TYPE_OTHER, self::ATTACHMENT_TYPE_AUDIO])) {
                copy($tmpFilesDir.'/'.$attachment, $attachmentsDir.'/'.$attachment);
            }
        }
    }
    
    public static function getSearchQuery($id = false, $author = null, $text = '', $tags = []) {
        $qb = \Bingo::$em->createQueryBuilder()
            ->select('DISTINCT e')
            ->from('App\Models\Entry', 'e')
            ->where('e.deleted = false')
            ->add('orderBy','e.created DESC, e.id DESC');
        
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
            $qb->addSelect('COUNT(t.id) as HIDDEN tag_count');
            $qb->join('Meta\Models\Tag', 't', 'WITH', 't.owner_id = e.id AND t.type = :tag_type');
            $qb->andWhere('t.value IN (:tags)');
            $qb->groupBy('e.id');
            $qb->having('tag_count = :tag_count');
            $qb->setParameter('tags', $tags);
            $qb->setParameter('tag_type', static::class);
            $qb->setParameter('tag_count', count($tags));
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
    
    public static function resizeAttachedImage($imagePath, $destinationPath, $width, $height) {
        try {
            $file = \PhpThumb\Factory::create($imagePath, ['resizeUp' => false]);
            $file->resize($width, $height);
            $file->save($destinationPath);
        } catch (\Exception $e) {
            trigger_error($e->getMessage());
        }
    }
    
    public function getTags() {
        return \Meta\Models\Tag::getTags($this); 
    }
    
    public function setTags($tags) {
        return \Meta\Models\Tag::setTags($this, $tags); 
    }
}