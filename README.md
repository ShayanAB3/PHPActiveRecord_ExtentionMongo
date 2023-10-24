# PHPActiveRecord_ExtentionMongo
This is an enhanced version of PHP ActiveRecord using MongoDB. 
Original library Php-ActiveRecord: https://github.com/php-activerecord/activerecord
Original library MongoDB: https://github.com/mongodb/mongo-php-library

## Requirements
- PHP 8.1-7.4
- MongoDB Driver

## Initial instructions
Before installing the library, you need to install the `PHP MongoDB` driver.
This can be done using:
- [PECL](https://www.php.net/manual/ru/mongodb.installation.pecl.php).
- Installing the `php_mongo.dll` driver directly to the php directory. [Documentation](https://www.php.net/manual/ru/mongodb.installation.windows.php).
- Building the PHP MongoDB driver from source. [Documentation](https://www.php.net/manual/en/mongodb.installation.manual.php).

## ORM Setup
Example Setup:
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

ActiveRecord\Config::initialize(function($config){
    $config->set_connections([
        'mongodb' => 'mongodb://localhost:27017/MongoDB',
        'mysql' => 'mysql://root:@127.0.0.1/verseny',
    ]);
});
```
CRUD operation can you check to index.php
## Create
Example create:
```php
  Users::create([
    "name" => "Nikita",
    "age" => 19,
    "email" => "nikith@gmail.com"
]);
```
OR
```php
  $user->name = "Nikita";
  $user->age = "19";
  $user->email = "nikita@mail.ru";
  $user->save();
```
OR
```php
  echo Users::find(["age"=>34])->name;
  var_dump(Users::count_by_age(34));
```
## Read
Example read:
```php
  //Find by id
  $user = Users::find(2);
  //Get first
  $userFind = Users::first();
```

## Update
Example update:
```php
  $user = Users::find(1);
  $user->age = 30;
  $user->save();
```
## Delete
Example delete:
```php
  $user = Users::find(2);
  $user->delete();
```



