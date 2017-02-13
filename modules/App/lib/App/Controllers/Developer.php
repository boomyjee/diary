<?php

namespace App\Controllers;

use App\Models\Entry;

class Developer extends BasePrivate {  
    
    public function sync_entries_with_vk_group() {
        ini_set("memory_limit", "2000M");
        ini_set("max_execution_time", 3600);
        
        if (empty($_GET['group_id'])) return true;    
        
        $group_id = $_GET['group_id'];
        $vk_auth_domain = 'https://oauth.vk.com';
        $vk_api_domain = 'https://api.vk.com';
        $vk_app_client_id = '5872208';
        $vk_app_client_secret = 'EaRegLSg7jONHogB6LIW';
        
        if (empty($_GET['code'])) {
            $params = array(
                'client_id' => $vk_app_client_id,
                'display' => 'page',
                'redirect_uri' => 'https://uxcandy.com'.base_url().'/developer/sync-entries-with-vk-group?group_id='.$group_id,
                'scope' => 'photos,wall',
                'response_type' => 'code',
                'v' => '5.62'
            );

            echo '<a href= ' . $vk_auth_domain . '/authorize?' . urldecode(http_build_query($params)) . '>Получить записи из группы в vk</a>';
        } else {
            $request = function($base_url, $params) {
                return json_decode(file_get_contents($base_url . '?' . urldecode(http_build_query($params))), true);
            };

            $params = [
                'client_id' => $vk_app_client_id,
                'client_secret' => $vk_app_client_secret,
                'code' => $_GET['code'],
                'redirect_uri' => 'https://uxcandy.com'.base_url().'/developer/sync-entries-with-vk-group?group_id='.$group_id
            ];
            $response = $request($vk_auth_domain . '/access_token', $params);
            $user_id = $response['user_id'];
            $access_token = $response['access_token'];

            $tmp_files_dir = INDEX_DIR . '/tmp_files';
            $tmp_imgs_preview_dir = $tmp_files_dir . '/images_preview';
            $tmp_resized_imgs_dir = $tmp_files_dir . '/resized_images';
            
            $directories = [$tmp_files_dir, $tmp_imgs_preview_dir, $tmp_resized_imgs_dir];
            foreach ($directories as $dir) 
                if (!file_exists($dir)) mkdir($dir, 0755, true);
            
            $saved_post_ids = \CMS\Models\Option::get('saved_post_ids') ?: [];
            $limit = 100;
            $page = 1;
            $max_pages = 50;
            do {
                $offset = ($page - 1) * $limit;
                $response = $request($vk_api_domain . '/method/wall.get', [
                    'owner_id' => '-'.$group_id,
                    'v' => '5.62',
                    'count' => $limit,
                    'offset' => $offset,
                    'access_token' => $access_token
                ])['response'];
            
                foreach ($response['items'] as $post) {
                    if (in_array($post['id'], $saved_post_ids)) continue;
                    
                    $attachments = [];
                    $album_id = false;
                    if (!empty($post['attachments'])) {
                        foreach ($post['attachments'] as $attachment) {
                            $photo = $attachment['photo'];
                            if (!$album_id && $photo['album_id'] && $photo['album_id'] > 0)
                                $album_id = $photo['album_id'];

                            foreach ([2560, 1280, 807, 604, 130] as $size) {
                                $key = 'photo_'.$size;
                                if (!empty($photo[$key])) {
                                    $attachments[] = $photo[$key];
                                    break;
                                }
                            }
                        }
                    }

                    if ($album_id) {
                        $response = $request($vk_api_domain . '/method/photos.get', [
                            'owner_id' => $user_id,
                            'album_id' => $album_id,
                            'v' => '5.62',
                            'access_token' => $access_token
                        ])['response'];

                        foreach ($response['items'] as $photo) {
                            foreach ([2560, 1280, 807, 604, 130] as $size) {
                                $key = 'photo_'.$size;
                                if (!empty($photo[$key])) {
                                    if (!in_array($photo[$key], $attachments))
                                        $attachments[] = $photo[$key];
                                    break;
                                }
                            }
                        }
                        usleep(100000);
                    }
                    
                    $entry_attachments = [];
                    if (!empty($attachments)) {
                        foreach($attachments as $attachment) {
                            $attachment_name = uniqid().'_'.pathinfo($attachment, PATHINFO_BASENAME);
                            $entry_attachments[] = $attachment_name;

                            $local_path = $tmp_files_dir . '/' . $attachment_name;
                            file_put_contents($local_path, file_get_contents($attachment));

                            Entry::resizeAttachedImage($tmp_files_dir . '/' . $attachment_name, $tmp_imgs_preview_dir . '/' . $attachment_name, 500, 500); 
                            Entry::resizeAttachedImage($tmp_files_dir . '/' . $attachment_name, $tmp_resized_imgs_dir . '/' . $attachment_name, 1280, 950);
                        }
                    }

                    $entry = new Entry;
                    $entry->text = $post['text'];
                    $entry->attachments = $entry_attachments;
                    $entry->attachment_preview_settings = [
                        'first_row_height_percent' => 40,
                        'secondary_rows_height_percent' => 20,
                        'visible_row_count' => 2
                    ];
                    $entry->author = $this->user;
                    $entry->save(); 
                    
                    $saved_post_ids[] = $post['id'];
                }
                usleep(300000);
            } while (!empty($response['items']) && $page++ < $max_pages);

            \CMS\Models\Option::set('saved_post_ids', $saved_post_ids);
            
            $cron = new \App\Controllers\Cron;
            $cron->sync_entries();
            
            redirect('entries');
        }
    }
}