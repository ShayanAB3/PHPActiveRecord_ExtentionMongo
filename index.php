<?php
require_once 'app/start.php';

use Codecourse\Users\Users;
use ActiveRecord\Table;
use foo\bar\biz\User;

$user = new Users();
echo "<pre>";
echo "<br>";

//var_dump(Users::all());

/*
$callback = function (\MongoDB\Driver\Session $session) use ($client): void {
    $client
        ->selectCollection('MongoDB', 'users')
        ->insertOne(["name" => "Semen","age" => 28,"email" => "semen@gmail.com"], ['session' => $session]);
    throw new Exception("Ошибка!!");
    $client
        ->selectCollection('MongoDB', 'users')->deleteOne(["age" => 28]);
};
// Step 2: Start a client session.
$session = $client->startSession();
// Step 3: Use with_transaction to start a transaction, execute the callback, and commit (or abort on error).
$transactionOptions = [
    'readConcern' => new \MongoDB\Driver\ReadConcern(\MongoDB\Driver\ReadConcern::LOCAL),
    'writeConcern' => new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000),
    'readPreference' => new \MongoDB\Driver\ReadPreference(\MongoDB\Driver\ReadPreference::RP_PRIMARY),
];
\MongoDB\with_transaction($session, $callback, $transactionOptions);
*/

$data = Users::find('all');
foreach ($data as &$result) {
    var_dump($result->id);
}

/*
Users::create([
    "name" => "Nikita",
    "age" => 19,
    "email" => "nikith@gmail.com"
]);
*/

/*
$userFind = Users::first();
var_dump($userFind->id);
*/

/*
$users = Users::find("all",["age" => 34]);
foreach ($users as &$result) {
    var_dump($result->age);
}*/


/*
foreach($users as $user1){
    echo $user1->name . '<br>';
}*/


/*
$user = Users::find(1);
$user->age = 30;
$user->save();
*/

/*
$user->name = "Nikita";
$user->age = "19";
$user->email = "nikita@mail.ru";
$user->save();
*/

/*
$user = Users::find(2);
$user->delete();
*/
/*
$conn = Users::connection()->connection;
Users::transaction(function() use($conn){

    var_dump($conn->{"MongoDB"}->{"users"}->findOne());
    return true;
});*/

//echo Users::find(["age"=>34])->name;
//var_dump(Users::count_by_age(34));



