<?php

/**
 * @package ActiveRecord
 */

namespace ActiveRecord;


use Closure;
use PDO;
use PDOException;

/**
 * The base class for database connection adapters.
 *
 * @package ActiveRecord
 */
abstract class SQLConnection implements IConnection
{
    /**
     * The DateTime format to use when translating other DateTime-compatible objects.
     *
     * NOTE!: The DateTime "format" used must not include a time-zone (name, abbreviation, etc) or offset.
     * Including one will cause PHP to ignore the passed in time-zone in the 3rd argument.
     * See bug: https://bugs.php.net/bug.php?id=61022
     *
     * @var string
     */
    const DATETIME_TRANSLATE_FORMAT = 'Y-m-d\TH:i:s';

    /**
     * The PDO connection object.
     *
     * @var mixed
     */
    public $connection;
    /**
     * The last query run.
     *
     * @var string
     */
    public $last_query;
    /**
     * Switch for logging.
     *
     * @var bool
     */
    private $logging = false;
    /**
     * Contains a Logger object that must impelement a log() method.
     *
     * @var object
     */
    private $logger;
    /**
     * The name of the protocol that is used.
     *
     * @var string
     */
    public $protocol;
    /**
     * Database's date format
     *
     * @var string
     */
    public static $date_format = 'Y-m-d';
    /**
     * Database's datetime format
     *
     * @var string
     */
    public static $datetime_format = 'Y-m-d H:i:s T';
    /**
     * Default PDO options to set for each connection.
     *
     * @var array
     */
    public static $PDO_OPTIONS = [
        PDO::ATTR_CASE => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false];
    /**
     * The quote character for stuff like column and field names.
     *
     * @var string
     */
    public static $QUOTE_CHARACTER = '`';
    /**
     * Default port.
     *
     * @var int
     */
    public static $DEFAULT_PORT = 0;

    /**
     * Retrieve a database connection.
     *
     * @param string $connection_string_or_connection_name A database connection string (ex. mysql://user:pass@host[:port]/dbname)
     *                                                     Everything after the protocol:// part is specific to the connection adapter.
     *                                                     OR
     *                                                     A connection name that is set in ActiveRecord\Config
     *                                                     If null it will use the default connection specified by ActiveRecord\Config->set_default_connection
     *
     * @return Connection
     *
     * @see parse_connection_url
     */
    public function getBuilder($table){
		return new SQLBuilder($this,$table);
	}
	public function getFetch($sth){
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}
    public function getValues($value){
        return array_values($value);
    }
    /**
     * Class Connection is a singleton. Access it via instance().
     *
     * @param array $info Array containing URL parts
     *
     * @return Connection
     */
    protected function __construct($info)
    {
        try {
            // unix sockets start with a /
            if ('/' != $info->host[0]) {
                $host = "host=$info->host";

                if (isset($info->port)) {
                    $host .= ";port=$info->port";
                }
            } else {
                $host = "unix_socket=$info->host";
            }

            $this->connection = new PDO("$info->protocol:$host;dbname=$info->db", $info->user, $info->pass, static::$PDO_OPTIONS);
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }
    }

    /**
     * Retrieves column meta data for the specified table.
     *
     * @param string $table Name of a table
     *
     * @return array an array of {@link Column} objects
     */
    public function columns($table)
    {
        $columns = [];
        $sth = $this->query_column_info($table);

        while (($row = $sth->fetch())) {
            $c = $this->create_column($row);
            $columns[$c->name] = $c;
        }

        return $columns;
    }

    /**
     * Escapes quotes in a string.
     *
     * @param string $string the string to be quoted
     *
     * @return string the string with any quotes in it properly escaped
     */
    public function escape($string)
    {
        return $this->connection->quote($string);
    }

    /**
     * Retrieve the insert id of the last model saved.
     *
     * @param string $sequence Optional name of a sequence to use
     *
     * @return int
     */
    public function insert_id($sequence=null)
    {
        return $this->connection->lastInsertId($sequence);
    }

    /**
     * Execute a raw SQL query on the database.
     *
     * @param string $sql     raw SQL string to execute
     * @param array  &$values Optional array of bind values
     *
     * @return mixed A result set object
     */
    public function query($sql, &$values=[])
    {
       
        if ($this->logging) {
            $this->logger->log($sql);
            if ($values) {
                $this->logger->log($values);
            }
        }

        $this->last_query = $sql;

        try {
            if (!($sth = $this->connection->prepare($sql))) {
                throw new DatabaseException($this);
            }
        } catch (PDOException $e) {
            throw new DatabaseException($this);
        }

        $sth->setFetchMode(PDO::FETCH_ASSOC);

        try {
            if (!$sth->execute($values)) {
                throw new DatabaseException($this);
            }
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }

        return $sth;
    }

    /**
     * Execute a query that returns maximum of one row with one field and return it.
     *
     * @param string $sql     raw SQL string to execute
     * @param array  &$values Optional array of values to bind to the query
     *
     * @return string
     */
    public function query_and_fetch_one($sql, &$values=[])
    {
        $sth = $this->query($sql, $values);
        $row = $sth->fetch(PDO::FETCH_NUM);

        return $row[0];
    }

    /**
     * Execute a raw SQL query and fetch the results.
     *
     * @param string  $sql     raw SQL string to execute
     * @param Closure $handler closure that will be passed the fetched results
     */
    public function query_and_fetch($sql, Closure $handler)
    {
        $sth = $this->query($sql);

        while (($row = $sth->fetch(PDO::FETCH_ASSOC))) {
            $handler($row);
        }
    }

    /**
     * Returns all tables for the current database.
     *
     * @return array array containing table names
     */
    public function tables()
    {
        $tables = [];
        $sth = $this->query_for_tables();

        while (($row = $sth->fetch(PDO::FETCH_NUM))) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    /**
     * Starts a transaction.
     */
    public function transaction()
    {
        if (!$this->connection->beginTransaction()) {
            throw new DatabaseException($this);
        }
    }

    /**
     * Commits the current transaction.
     */
    public function commit()
    {
        if (!$this->connection->commit()) {
            throw new DatabaseException($this);
        }
    }

    /**
     * Rollback a transaction.
     */
    public function rollback()
    {
        if (!$this->connection->rollback()) {
            throw new DatabaseException($this);
        }
    }

    /**
     * Tells you if this adapter supports sequences or not.
     *
     * @return bool
     */
    public function supports_sequences()
    {
        return false;
    }

    /**
     * Return a default sequence name for the specified table.
     *
     * @param string $table       Name of a table
     * @param string $column_name Name of column sequence is for
     *
     * @return string sequence name or null if not supported
     */
    public function get_sequence_name($table, $column_name)
    {
        return "{$table}_seq";
    }

    /**
     * Return SQL for getting the next value in a sequence.
     *
     * @param string $sequence_name Name of the sequence
     *
     * @return string
     */
    public function next_sequence_value($sequence_name)
    {
        return null;
    }

    /**
     * Quote a name like table names and field names.
     *
     * @param string $string string to quote
     *
     * @return string
     */
    public function quote_name($string)
    {
        return $string[0] === static::$QUOTE_CHARACTER || $string[strlen($string) - 1] === static::$QUOTE_CHARACTER ?
            $string : static::$QUOTE_CHARACTER . $string . static::$QUOTE_CHARACTER;
    }

    /**
     * Return a date time formatted into the database's date format.
     *
     * @param DateTime $datetime The DateTime object
     *
     * @return string
     */
    public function date_to_string($datetime)
    {
        return $datetime->format(static::$date_format);
    }

    /**
     * Return a date time formatted into the database's datetime format.
     *
     * @param DateTime $datetime The DateTime object
     *
     * @return string
     */
    public function datetime_to_string($datetime)
    {
        return $datetime->format(static::$datetime_format);
    }

    /**
     * Converts a string representation of a datetime into a DateTime object.
     *
     * @param string $string A datetime in the form accepted by date_create()
     *
     * @return object The date_class set in Config
     */
    public function string_to_datetime($string)
    {
        $date = date_create($string);
        $errors = \DateTime::getLastErrors();

        if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            return null;
        }

        $date_class = Config::instance()->get_date_class();

        return $date_class::createFromFormat(
            static::DATETIME_TRANSLATE_FORMAT,
            $date->format(static::DATETIME_TRANSLATE_FORMAT),
            $date->getTimezone()
        );
    }

    /**
     * Adds a limit clause to the SQL query.
     *
     * @param string $sql    the SQL statement
     * @param int    $offset row offset to start at
     * @param int    $limit  maximum number of rows to return
     *
     * @return string The SQL query that will limit results to specified parameters
     */
    abstract public function limit($sql, $offset, $limit);

    /**
     * Query for column meta info and return statement handle.
     *
     * @param string $table Name of a table
     *
     * @return PDOStatement
     */
    abstract public function query_column_info($table);

    /**
     * Query for all tables in the current database. The result must only
     * contain one column which has the name of the table.
     *
     * @return PDOStatement
     */
    abstract public function query_for_tables();

    /**
     * Executes query to specify the character set for this connection.
     */
    abstract public function set_encoding($charset);

    /*
     * Returns an array mapping of native database types
     */

    abstract public function native_database_types();

    /**
     * Specifies whether or not adapter can use LIMIT/ORDER clauses with DELETE & UPDATE operations
     *
     * @internal
     * @returns boolean (FALSE by default)
     */
    public function accepts_limit_and_order_for_update_and_delete()
    {
        return false;
    }
}