<?php

namespace ActiveRecord;

use Closure;

interface IConnection{
	public function accepts_limit_and_order_for_update_and_delete();
	public function string_to_datetime($string);
	public function datetime_to_string($datetime);
	public function date_to_string($datetime);
	public function quote_name($string);
	public function next_sequence_value($sequence_name);
	public function get_sequence_name($table, $column_name);
	public function rollback();
	public function commit();
	public function transaction();
	public function tables();
	public function query_and_fetch($sql, Closure $handler);
	public function query_and_fetch_one($sql, &$values=array());
	public function query($sql, &$values=array());
	public function insert_id($sequence=null);
	public function escape($string);
	public function columns($table);
	public function getBuilder($table);
}