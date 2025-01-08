<?php

namespace ActiveRecord;
use MongoDB\BSON\ObjectId;

//require __DIR__.'/interfaces/IBuilder.php';

class MongoBuilder implements IBuilder{
    private $connection;
	private $operation = 'FIND';
	private $table;
	private $select = '*';
	private $joins;
	private $order;
	private $limit;
	private $offset;
	private $group;
	private $having;
	private $update;
	private $values;

	// for where
	private $where;
	private $where_values = array();

	// for insert/update
	private $data;
	private $sequence;

    public function __construct($conn,$table)
    {  
        if (!$conn)
			throw new ActiveRecordException('A valid database connection is required.');
       $this->connection = $conn;
       $this->table = $table;
    }

    public function __toString()
	{
		return $this->to_s();
	}

	/**
	 * Returns the SQL string.
	 *
	 * @see __toString
	 * @return string
	 */
	public function to_s()
	{
		$func = 'build_' . strtolower($this->operation);
		return $this->$func();
	}

    public function bind_values()
	{
		$ret = array();

		if ($this->data)
			$ret = $this->data;

		if ($this->get_where_values())
			$ret = array_merge($ret,$this->get_where_values());

		return $ret;
	}

    public function get_where_values()
	{
		return $this->where;
	}

    public function where(/* (conditions, values) || (hash) */)
    {
        $this->apply_where_conditions(func_get_args());
        return $this;
    }
    public function order($order)
	{
		$this->order = $order;
		return $this;
	}

	public function group($group)
	{
		$this->group = $group;
		return $this;
	}

	public function having($having)
	{
		$this->having = $having;
		return $this;
	}

	public function limit($limit)
	{
		$this->limit = intval($limit);
		return $this;
	}

	public function offset($offset)
	{
		$this->offset = intval($offset);
		return $this;
	}

	public function select($select)
	{
		$this->operation = 'FIND';
		$this->select = $select;
		return $this;
	}

	public function joins($joins)
	{
		$this->joins = $joins;
		return $this;
	}

	public function insert($hash, $pk=null, $sequence_name=null)
	{
		if (!is_hash($hash))
			throw new ActiveRecordException('Inserting requires a hash.');

		$this->operation = 'INSERT';
		$this->data = $hash;

		if ($pk && $sequence_name)
			$this->sequence = array($pk,$sequence_name);

		return $this;
	}

	public function update($mixed)
	{
		$this->operation = 'UPDATE';

		if (is_hash($mixed))
			$this->data = $mixed;

		if (is_array($mixed))
			$this->update = $mixed;
		else
			throw new ActiveRecordException('Updating requires a hash or string.');

		return $this;
	}

	public function delete()
	{
		$this->operation = 'DELETE';
		$this->apply_where_conditions(func_get_args());
		return $this;
	}

	public function setValues($values){
		$this->values = $values;
	}

	/**
	 * Reverses an order clause.
	 */
	public static function reverse_order($order)
	{
		if (!trim($order))
			return $order;

		$parts = explode(',',$order);

		for ($i=0,$n=count($parts); $i<$n; ++$i)
		{
			$v = strtolower($parts[$i]);

			if (strpos($v,' asc') !== false)
				$parts[$i] = preg_replace('/asc/i','DESC',$parts[$i]);
			elseif (strpos($v,' desc') !== false)
				$parts[$i] = preg_replace('/desc/i','ASC',$parts[$i]);
			else
				$parts[$i] .= ' DESC';
		}
		return join(',',$parts);
	}

	/**
	 * Converts a string like "id_and_name_or_z" into a conditions value like array("id=? AND name=? OR z=?", values, ...).
	 *
	 * @param Connection $connection
	 * @param $name Underscored string
	 * @param $values Array of values for the field names. This is used
	 *   to determine what kind of bind marker to use: =?, IN(?), IS NULL
	 * @param $map A hash of "mapped_column_name" => "real_column_name"
	 * @return A conditions array in the form array(sql_string, value1, value2,...)
	 */
	public static function create_conditions_from_underscored_string($connection, $name, &$values=array(), &$map=null)
	{
		if (!$name)
			return null;

		$parts = preg_split('/(_and_|_or_)/i',$name,-1,PREG_SPLIT_DELIM_CAPTURE);
		$conditions = [];
		$row = [];
		$newParts = [];
		for($i = 0;$i < count($parts); $i++){
			if($parts[$i] !== "_and_" && $parts[$i] !== "_or_"){
				$row[] = $parts[$i] == "id" ? "_".$parts[$i] : $parts[$i];
			}
		}
		if(count($row) !== count($values)){
			throw new DatabaseException("Count and Values not valid!: ". count($row) ."!==". count($values));
		}
		
		for($i = 0,$j = 0;$i < count($parts); $i++){
			if($parts[$i] !== "_and_" && $parts[$i] !== "_or_"){
				$newParts[] = [$row[$j] => $values[$j]];
				$j++;
			}
			else{
				$newParts[] = $parts[$i];
			}
		}

		if(!in_array("_and_", $parts) && !in_array("_or_",$parts)){
			return $newParts;
		}
	
		foreach($newParts as $part){
			if($part == "_and_" || $part == "_or_"){
				$i = array_search($part,$parts);
				$keys = array_keys($newParts);

				$conditions[static::parse_or_and($part)] = [
					$newParts[$keys[$i-1]],
					$newParts[$keys[$i+1]]
				]; 
			}
		}	
		return $conditions;
	}
	private static function parse_or_and($value){
		return "$".str_replace("_","",$value);
	}

	/**
	 * Like create_conditions_from_underscored_string but returns a hash of name => value array instead.
	 *
	 * @param string $name A string containing attribute names connected with _and_ or _or_
	 * @param $args Array of values for each attribute in $name
	 * @param $map A hash of "mapped_column_name" => "real_column_name"
	 * @return array A hash of array(name => value, ...)
	 */
	public static function create_hash_from_underscored_string($name, &$values=array(), &$map=null)
	{
		$parts = preg_split('/(_and_|_or_)/i',$name);
		$hash = array();

		for ($i=0,$n=count($parts); $i<$n; ++$i)
		{
			// map to correct name if $map was supplied
			$name = $map && isset($map[$parts[$i]]) ? $map[$parts[$i]] : $parts[$i];
			$hash[$name] = $values[$i];
		}
		var_dump($hash);
		return $hash;
	}

	/**
	 * prepends table name to hash of field names to get around ambiguous fields when SQL builder
	 * has joins
	 *
	 * @param array $hash
	 * @return array $new
	 */
	private function prepend_table_name_to_fields($hash=array())
	{
		$new = array();
		$table = $this->connection->quote_name($this->table);

		foreach ($hash as $key => $value)
		{
			$k = $this->connection->quote_name($key);
			$new[$table.'.'.$k] = $value;
		}

		return $new;
	}

	private function apply_where_conditions($args)
	{
		
		require_once 'Expressions.php';
		$num_args = count($args);

		if ($num_args == 1 && is_hash($args[0]))
		{
			$hash = $args[0];
			//is_null($this->joins) ? $args[0] : $this->prepend_table_name_to_fields($args[0]);
			$this->where = $hash;
			$this->where_values = array_flatten(array_values($hash));
		}
		elseif ($num_args > 0)
		{
			// if the values has a nested array then we'll need to use Expressions to expand the bind marker for us
			$values = array_slice($args,1);

			foreach ($values as $name => &$value)
			{
				if (is_array($value))
				{
					$e = new Expressions($this->connection,$args[0]);
					$e->bind_values($values);
					$this->where = $args[0];
					$this->where_values = array_flatten(array_values($args[0]));
					return;
				}
			}
			
			// no nested array so nothing special to do
			$this->where = $args[0];
			$this->where_values = &$values;
		}
	}

	private function build_delete()
	{

		$mongo = [
			"operation" => $this->operation,
			"table" => $this->table
		];

		if ($this->where)
			$mongo["where"] = $this->where;

		if ($this->connection->accepts_limit_and_order_for_update_and_delete())
		{
			if ($this->order)
				$mongo["order"] = $this->order;

			if ($this->limit)
				$mongo["limit"] = $this->limit;
		
		}
		return $mongo;
	}

	private function build_insert()
	{
		return [
            "table" => $this->table,
            "operation"=> $this->operation
        ];
	}
   
	private function build_find()
	{
        $mongo = [
			"table" => $this->table,
			"operation"=> $this->operation
		];
		if($this->select){
			$mongo["select"] = $this->select;
		}
		if ($this->where)
			$mongo["where"] = $this->where;

		if ($this->group)
			$mongo["group"] = $this->group;

		if ($this->having)
			$mongo["having"] = $this->having;

		if ($this->order)
			$mongo["order"] = $this->order;

		if ($this->limit || $this->offset)
			$mongo["slice"] = $this->connection->limit($this->offset,$this->limit);

		return $mongo;
	}

	private function build_update()
	{
		$mongo = [
			"table" => $this->table,
			"operation"=> $this->operation
		];
		if($this->update){
			$mongo["update"] = $this->update;
		}

		if ($this->where)
			$mongo["where"] = $this->where;

		if ($this->connection->accepts_limit_and_order_for_update_and_delete())
		{
			if ($this->order)
				$mongo["order"] = $this->order;

			if ($this->limit)
				$mongo["slice"] = $this->connection->limit(null,$this->limit);
		}

		return $mongo;
	}

	private function quoted_key_names()
	{
		$keys = array();

		foreach ($this->data as $key => $value)
			$keys[] = $this->connection->quote_name($key);

		return $keys;
	}

	public function insertOperation($mongo){
		if(!$this->values){
			throw new DatabaseException("Values not found!");
		}
		$this->values["_id"] = $this->connection->insert_id($this->table) + 1;
		$this->connection->db->{$this->table}->insertOne($this->values);
	}
	
	public function findOperation($mongo){
		$options = [];
		$document = [];
		$where = isset($mongo["where"]) ? $mongo["where"] : [];

		if (isset($mongo["group"]))
			$options["group"] = $this->group;

		if (isset($mongo["having"]))
			$options["having"] = $this->having;

		if (isset($mongo["order"]))
			$options["sort"] = ['$natural'=> static::parse_any_desc($mongo["order"])];

		if (isset($mongo["slice"])){
			$sliceArray = $this->connection->limit($this->offset,$this->limit);
			foreach($sliceArray as $key => $slice){
				$options[$key] = $slice;
			}
		}

		$fetch = $this->connection->db->{$this->table}->find($where,$options);

		foreach($fetch as $db){
	
			$document[] = json_decode(json_encode($db), true); 
		}

		if($mongo["select"] == "COUNT(*)"){
			return count($document);
		}

		return $document;	
	}

	public static function parse_any_desc($order){
		return strpos($order,"DESC") ? -1 : 1;
	}

	public function updateOperation($mongo){
		
		if(!isset($mongo["update"])){
			throw new DatabaseException("Update not found!");
		}
		if(!isset($mongo["where"])){
			throw new DatabaseException("Where not found!");
		}
		$options = [];
		$update = ['$set' => $mongo["update"]];
	
		if ($this->connection->accepts_limit_and_order_for_update_and_delete())
		{
			if (isset($mongo["order"]))
				$options["order"] = $this->order;

			if (isset($mongo["slice"]))
				$options["slice"] = $this->connection->limit(null,$this->limit);
		}

		$updateOp = $this->connection->db->{$this->table}->updateMany($mongo["where"],$update,$options);
		//var_dump($updateOp->getModifiedCount());
	}

	public function deleteOperation($mongo){
		if(!isset($mongo["where"])){
			throw new DatabaseException("Where not found!");
		}
		$deleteOp = $this->connection->db->{$this->table}->deleteMany($mongo["where"]);
		//var_dump($deleteOp->getDeletedCount());
	}
}
