<?php

require 'vendor/autoload.php'; // Подключение автозагрузчика Composer

use MongoDB\Client;

try {
    // Создание экземпляра клиента MongoDB
    $client = new Client("mongodb://localhost:27017");

    // Подключение к базе данных
    $database = $client->selectDatabase('ORMPHP'); // Укажите имя вашей базы данных
    $collection = $database->selectCollection('users'); // Укажите имя вашей коллекции

    $result = $collection->find();
    
    foreach ($result as $doc) {
        var_dump($doc);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
