<?php

namespace ActiveRecord;

use MongoDB\Client;
use Closure;
use Exception;


abstract class MongoConnection implements IConnection{
    public $connection;
    public $protocol;
    public $logger;
    public $logging = false;
    public $db;
	static $QUOTE_CHARACTER = '';
    const DATETIME_TRANSLATE_FORMAT = 'Y-m-d\TH:i:s';
    static $date_format = 'Y-m-d';
	static $datetime_format = 'Y-m-d H:i:s T';
	public $session;
	private $values;
    public function __construct($info)
    {
        $this->connection = new Client($info->protocol."://".$info->host.":".$info->port);
        $this->db = $this->connection->{$info->db};
    }
	public function getBuilder($table)
	{
		return new MongoBuilder($this,$table);
	}
	
    public function quote_name($string)
	{
		return $string[0] === static::$QUOTE_CHARACTER || $string[strlen($string) - 1] === static::$QUOTE_CHARACTER ?
			$string : static::$QUOTE_CHARACTER . $string . static::$QUOTE_CHARACTER;
	}
	public function getValues($values){
		return $values;
	}
    public function columns($table)
	{
		$columns = array();
		$sth = $this->query_column_info($table);
		
		foreach($sth as $row) {
			$c = $this->create_column($row);
			$columns[$c->name] = $c;
		}
		return $columns;
	}
    function supports_sequences()
	{
		return false;
	}
    public function tables()
	{
		$tables = array();
		$sth = $this->query_for_tables();

		foreach($sth as $table){
            $tables[] = $table;
        }

		return $tables;
	}
    public function get_sequence_name($table, $column_name)
	{
		return "{$table}_seq";
	}
    public function next_sequence_value($sequence_name)
	{
		return null;
	}
    public function date_to_string($datetime)
	{
		return $datetime->format(static::$date_format);
	}

	public function datetime_to_string($datetime)
	{
		return $datetime->format(static::$datetime_format);
	}

	public function string_to_datetime($string)
	{
		$date = date_create($string);
		$errors = \DateTime::getLastErrors();

		if ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
			return null;

		$date_class = Config::instance()->get_date_class();

		return $date_class::createFromFormat(
			static::DATETIME_TRANSLATE_FORMAT,
			$date->format(static::DATETIME_TRANSLATE_FORMAT),
			$date->getTimezone()
		);
	}
	public function transaction()
	{
		
		$this->session = $this->connection->startSession();
		$this->session->startTransaction();
		if (!$this->session)
			throw new DatabaseException($this);
			
	}
	public function commit()
	{
		$this->session->commitTransaction();
	}

	public function rollback()
	{
		$this->session->abortTransaction();
	}
	public function query_and_fetch_one($sql, &$values=array())
	{
		
		$sth = $this->query($sql, $values);
		//$row = $sth->fetch(PDO::FETCH_NUM);
		return $sth;
		
	}
	public function accepts_limit_and_order_for_update_and_delete()
	{
		return false;
	}
	public function insert_id($sequence = null)
	{
		$lastId = $this->db->{$sequence}->findOne([], ["sort"=>['$natural'=> -1]])->_id;
		if(!is_numeric($lastId)){
			throw new DatabaseException("Is not Number");
		}
		return (int)$lastId;
	}
	public function escape($string)
	{
		return quotemeta($string);
	}
	public function query($mongo, &$values=null)
	{
		$operation = strtolower($mongo["operation"]) . "Operation";
		$table = $this->getBuilder($mongo["table"]); 
		if(isset($values)){
			$table->setValues($values);
		}
		return $table->$operation($mongo);
	}

	public function getFetch($sth){
		return $sth;
	}

	public function query_and_fetch($sql, Closure $handler)
	{
		/*
		$sth = $this->query($sql);

		while (($row = $sth->fetch(PDO::FETCH_ASSOC)))
			$handler($row);
			*/
	}


    abstract public function limit($offset, $limit);

    abstract public function query_column_info($table);

    abstract public function query_for_tables();

    abstract public function set_encoding($charset);

    abstract public function native_database_types();

}