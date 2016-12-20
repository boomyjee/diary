<?php

namespace App\Controllers\Admin;

class Users extends \CMS\Controllers\Admin\BasePrivate {
    
    function user_list() {
        $filterForm = new \CMS\FilterForm;
        $filterForm->text('id');
        $filterForm->text('login');
        $filterForm->text('email');
        
        $criteria = array();
        if ($filterForm->validate()) {
            $id = $filterForm->values['id'];
            if ($id) $criteria['id'] = $id;
            $login = trim($filterForm->values['login']);
            if ($login) $criteria['login'] = "LIKE %".$login."%";
            $email = trim($filterForm->values['email']);
            if ($email) $criteria['email'] = "LIKE %".$email."%";
        }
        
        $_GET['sort_by'] = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
        $_GET['sort_order'] = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
        $query = \App\Models\User::findByQuery($criteria, $_GET['sort_by']." ".$_GET['sort_order']);
        $pagination = new \Bingo\Pagination(20, $this->getPage(), $total = false, $pattern = false, $query);
        
        $this->data['filter_form'] = $filterForm;
        $this->data['pagination'] = $pagination->get(10);
        $this->data['list'] = $pagination->result();

        $this->data['fields']['id'] = _t('ID');
        $this->data['fields']['login'] = _t('Имя');
        $this->data['fields']['email'] = _t('Email');
        
        $this->data['sort_fields'] = ['id', 'login', 'email'];        
        $this->data['page_actions']['admin/app/user-edit'] = _t('Создать нового');
        $this->data['item_actions']['admin/app/user-edit'] = _t('редактировать');
        
        $this->data['title'] = _t('Список пользователей');
        $this->view('cms/base-list');
    }
    
    function user_edit($id) { 
        $user = \App\Models\User::findOrCreate($id);
        
        $form = new \Form('post');
        $form->fieldset(_t('Данные пользователя'));
        $form->text('login', _t('Логин'), 'required', $user->login);
        $form->text('email', _t('Email'), ['required', 'valid_email'], $user->email);
        $form->text('new_password', _t('Новый пароль'), false);
        $form->submit(_t('Сохранить'));
        
        if ($form->validate()) {
            $form->fill($user);
            if ($form->values['new_password']) {
                $user->setPassword($form->values['new_password']);
            }
            $user->save();
            
            set_flash('info',_t('Изменения сохранены успешно'));
            redirect('admin/app/user-edit/'.$user->id);
        }
        
        $this->data['title'] = _t('Редактирование пользователя');
        $this->data['form'] = $form->get();
        $this->view('cms/base-edit');
    }
}