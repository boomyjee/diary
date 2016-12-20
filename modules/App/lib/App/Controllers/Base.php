<?php

namespace App\Controllers;

class Base extends \Bingo\Controller {
    
    public function __construct() {
        parent::__construct();
        $this->user = $this->data['user'] = \App\Models\User::checkLoggedIn();
    }
    
    protected function getPage() {
        if (isset($_GET['p'])) $page = (int)$_GET['p']; else $page = 1;if ($page<=1) $page = 1;
        return $page;
    }
}