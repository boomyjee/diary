<?php

namespace App\Models;

/**
* @Entity
* @Table(name="app_users")
*/
class User extends \Auth\Models\User {
    
    public function setPassword($password) {
        $this->password = md5($password);
    }
}