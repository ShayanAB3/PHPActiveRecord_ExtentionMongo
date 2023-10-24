<?php

namespace Codecourse\Users;

use ActiveRecord\Model as ActiveRecord;

class Users extends ActiveRecord{
    static $connection = "mysql";
}