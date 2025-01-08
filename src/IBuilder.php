<?php

namespace ActiveRecord;

interface IBuilder{
    public function __toString();
    public function to_s();
    public function bind_values();
    public function get_where_values();
    public function where();
    public function order($order);
    public function group($group);
    public function having($having);
    public function limit($limit);
    public function offset($offset);
    public function select($select);
    public function joins($joins);
    public function insert($hash, $pk=null, $sequence_name=null);
    public function update($mixed);
    public function delete();
    public static function reverse_order($order);
}