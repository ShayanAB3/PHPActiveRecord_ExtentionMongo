<?php

require_once __DIR__."/vendor/autoload.php";

use ActiveRecord\Config;
use ActiveRecord\MongoBuilder;
use Models\Courses;
use Models\Users;
use ActiveRecord\MongoConnection;

$cfg = Config::instance();
$cfg->set_connections([
    'development' => 'mysql://root:111@localhost/lab5',
    'mondodb' => 'mongodb://localhost:27017/ORMPHP'
]);

$users = Users::find(2);
#$course->department_id = 1;
#$course->save();
$courses = Courses::find(2);
var_dump($users->name);