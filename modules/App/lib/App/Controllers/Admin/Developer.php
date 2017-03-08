<?php

namespace App\Controllers\Admin;

class Developer extends \CMS\Controllers\Admin\BasePrivate {
    
    function test() {

        $e = \App\Models\Entry::find(173);
        $e->synced = false;
        $e->save();
        _D($e);

    }
}