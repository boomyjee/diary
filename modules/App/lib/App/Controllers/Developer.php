<?php

namespace App\Controllers;

use App\Models\Entry;

class Developer extends Base {  
    
    public function sync_json() {
        $entries = json_decode(file_get_contents(INDEX_DIR."/diary.json"));
        foreach ($entries as $one) {
            $entry = new Entry;
            $entry->id = $one->id;
            $entry->text = $one->text;
            $entry->attachments = $one->images;
            $entry->attachment_preview_settings = [
                'first_row_height_percent' => 40,
                'secondary_rows_height_percent' => 20,
                'visible_row_count' => 2
            ];
            $entry->author = $this->user;
            $entry->save();
            _D($entry->id);
        }
    }
}