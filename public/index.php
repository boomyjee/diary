<?php

define('BINGO_PATH',__DIR__.'/../../bingo');
require_once BINGO_PATH . "/loader.php";

ini_set('display_errors',1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Kiev');

\Bingo\Configuration::$applicationMode = 'development';
\Bingo\Configuration::$locale = 'ru_RU';

\Bingo\Configuration::addModulePath(INDEX_DIR."/../modules");
\Bingo\Configuration::addModules('Auth','Meta','CMS','App');

require __DIR__."/../db.php";
\Bingo\Template::addIncludePath('',BINGO_PATH."/template",INDEX_URL."/../../bingo/template");
\Bingo\Template::addIncludePath('', INDEX_DIR."/../template", INDEX_URL."/../template");

\CMS\Configuration::$log_errors = true;

\Bingo\Bingo::getInstance()->run();