<?php

namespace App\Controllers;

class Base extends \Bingo\Controller {

    public function __construct($checkUser = true) {
        parent::__construct();
        $this->user = $this->data['user'] = \App\Models\User::checkLoggedIn();
        if ($checkUser && !$this->user)
            redirect('login');    
    }
}