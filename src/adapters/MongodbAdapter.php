<?php

namespace ActiveRecord;


class MongodbAdapter extends MongoConnection{

	public function limit($offset = null,mixed $limit = null){
		return is_null($offset) ? ["limit" => intval($limit)] : ["skip" => intval($offset),"limit" => intval($limit)];;
	}

    public function query_for_tables() : array
	{
        $collections = [];
		foreach($this->db->listCollections() as $collection){
            $collections[] = $collection['name'];
        }
        return $collections;
	}
    public function query_column_info($table) : array
	{
		$columns = [];
		$columnData = $this->db->{$table}->findOne();
        if(isset($columnData)){
			foreach($columnData as $key => $column){
				$columns[] = [
					'field' => $key,
					'null' =>  $key === "_id" ? '':'NO',
					'key' => $key === "_id" ? 'PRI':'',
					'extra' => $key === "_id" ? 'auto_increment':'',
					'type' => static::getTypeColumn($column),
					'default' => null
				];
			}
		}
		else{
			throw new DatabaseException("Пожалуста,заполните таблицу, в ручную");
		}
        return $columns;
	}

    public static function getTypeColumn($column) : string
	{
        $formatArray = ['datetime'=>'Y-m-d','timestamp'=>'h:i:s'];
        foreach($formatArray as $key => $format){
           if(static::validateDate($column,$format)){
                return $key;
           }
        }
        return gettype($column);
    }

    public static function validateDate($date, $format = 'Y-m-d') : string | false
	{
        if(gettype($date) === 'string'){
            $d = DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) === $date;
        }
        return false;
    }

    public function create_column(&$column)
	{
		$c = new Column();
		$c->inflected_name	= Inflector::instance()->variablize($column['field']);
		$c->name			= $column['field'];
		$c->nullable		= ($column['null'] === 'YES' ? true : false);
		$c->pk				= ($column['key'] === 'PRI' ? true : false);
		$c->auto_increment	= ($column['extra'] === 'auto_increment' ? true : false);

		if ($column['type'] == 'timestamp' || $column['type'] == 'datetime')
		{
			$c->raw_type = 'datetime';
			$c->length = 19;
		}
		elseif ($column['type'] == 'date')
		{
			$c->raw_type = 'date';
			$c->length = 10;
		}
		elseif ($column['type'] == 'time')
		{
			$c->raw_type = 'time';
			$c->length = 8;
		}
		else
		{
			preg_match('/^([A-Za-z0-9_]+)(\(([0-9]+(,[0-9]+)?)\))?/',$column['type'],$matches);

			$c->raw_type = (count($matches) > 0 ? $matches[1] : $column['type']);

			if (count($matches) >= 4)
				$c->length = intval($matches[3]);
		}

		$c->map_raw_type();
		$c->default = $c->cast($column['default'],$this);

		return $c;
	}

	public function accepts_limit_and_order_for_update_and_delete() { return true; }

	public function native_database_types()
	{
		return array(
			'primary_key' => 'int(11) UNSIGNED DEFAULT NULL auto_increment PRIMARY KEY',
			'string' => array('name' => 'varchar', 'length' => 255),
			'text' => array('name' => 'text'),
			'integer' => array('name' => 'int', 'length' => 11),
			'float' => array('name' => 'float'),
			'datetime' => array('name' => 'datetime'),
			'timestamp' => array('name' => 'datetime'),
			'time' => array('name' => 'time'),
			'date' => array('name' => 'date'),
			'binary' => array('name' => 'blob'),
			'boolean' => array('name' => 'tinyint', 'length' => 1)
		);
	}

	public function set_encoding($charset){

	}
}