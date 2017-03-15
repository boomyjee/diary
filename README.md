<p align="center">
    <img alt="Diary" title="Diary" src="/images/diary.jpg">
</p>

<p align="center">
    <img alt="license" src="https://img.shields.io/badge/license-MIT-blue.svg">
    <img alt="php" src="https://img.shields.io/badge/php-%3E%3D5.6-blue">
    <img alt="awesome" src="https://camo.githubusercontent.com/fef0a78bf2b1b477ba227914e3eff273d9b9713d/68747470733a2f2f696d672e736869656c64732e696f2f62616467652f617765736f6d652533462d796573212d627269676874677265656e2e737667">
    <img alt="state" src="https://img.shields.io/badge/state-success-lightgrey">
</p>

<p align="center">
    Web application for personal user diaries.
</p>

## Description

The application backs everything up to `cloud.mail.ru` for safety.

Application supports media layout fine tuning:

First toolbar to set the first image size.  
Second toolbar to set the second image size.  
Third toolbar to set number of rows to be shown.

![awesome image toolbar demo](images/images_view.gif)

## Installation

- create an empty database
- copy `db.php.sample` into `db.php` and set the database access credentials
- copy `config.php.sample` into `config.php` and set access credentials to the mail.ru storage
- run `<your_domain>/install` and enter password for 'admin' user
- in `<your_domain>/admin` area use "admin" login and password set by you during installation
- go to `/admin/app/user-list` and create a new user

Now you can log in into the diary

### License

Application is [MIT licensed](./LICENSE).
