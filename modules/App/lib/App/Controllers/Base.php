<?php

namespace App\Controllers;

class Base extends \Bingo\Controller {
    public $checkUser = true;
    
    public function __construct() {
        parent::__construct();
        $this->user = $this->data['user'] = \App\Models\User::checkLoggedIn();
        if ($this->checkUser && !$this->user)
            redirect('login');    
    }
}