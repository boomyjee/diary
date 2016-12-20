<?php

namespace App\Controllers;

class BasePrivate extends Base {
    
    public function __construct() {
        parent::__construct();
        if (!$this->user) redirect('login');
    }
}