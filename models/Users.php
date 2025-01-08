<?php

namespace Models;

use ActiveRecord\Model;

class Users extends Model{
    static $connection = "mondodb";
}