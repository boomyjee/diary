<?php

namespace App\Controllers;

class Auth extends Base {
    public function __construct() {
        $this->checkUser = false;
        parent::__construct();
    }

    public function login() {
        if ($this->user) redirect('entries');
        
        $user = $this->user;
        $form = new \Form('post');
        $form->text('login', 'Логин', 'required', false, ['class'=>'form-control']);
        $form->password('password', 'Пароль', ['required', function($password) use (&$user) {
            $user = \App\Models\User::findOneBy(['login'=> $_POST['login']]);
            if ($user) {
                $successLogin = \App\Models\User::testLogin($user->login, $password);
                if ($successLogin) return $password;
            }
            throw new \ValidationException(_t('Неверный пароль'));
        }],false, ['class'=>'form-control']);
        $form->checkbox('remember_me', _t('Запомнить меня'), '', true);
        
        if ($form->validate()) {
            if ($user) {
                \App\Models\User::loginUser($user, $form->values['remember_me']);
                redirect('entries');
            }
        }
        
        $this->data['login_form'] = $form;
        $this->view('login');
    }
    
    function logout() {
        if ($this->user) {
            $this->user->logout();
        }
        redirect('login');
    }
}