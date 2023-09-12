<?php

require_once __DIR__ . '/../vendor/autoload.php';

ActiveRecord\Config::initialize(function($config){
    //$config->set_model_directory('models');
    $config->set_connections([
        'development' => 'mongodb://localhost:27017/MongoDB',
        //'development' => 'mysql://root:@127.0.0.1/test',
    ]);
});