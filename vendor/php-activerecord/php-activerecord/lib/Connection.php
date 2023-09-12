<?php

namespace ActiveRecord;

use PDOException;

require __DIR__.'/interfaces/IConnection.php';
require __DIR__.'/interfaces/IBuilder.php';
class Connection{
    
    public static function instance($connection_string_or_connection_name=null)
    {
        $config = Config::instance();

        if (false === strpos($connection_string_or_connection_name, '://')) {
            $connection_string = $connection_string_or_connection_name ?
                $config->get_connection($connection_string_or_connection_name) :
                $config->get_default_connection_string();
        } else {
            $connection_string = $connection_string_or_connection_name;
        }

        if (!$connection_string) {
            throw new DatabaseException('Empty connection string');
        }
        $info = static::parse_connection_url($connection_string);
        $fqclass = static::load_adapter_class($info->protocol);

        try {
            $connection = new $fqclass($info);
            $connection->protocol = $info->protocol;
            $connection->logging = $config->get_logging();
            $connection->logger = $connection->logging ? $config->get_logger() : null;

            if (isset($info->charset)) {
                $connection->set_encoding($info->charset);
            }
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }

        return $connection;
    }

    /**
     * Loads the specified class for an adapter.
     *
     * @param string $adapter name of the adapter
     *
     * @return string the full name of the class including namespace
     */
    private static function load_adapter_class($adapter)
    {
        $class = ucwords($adapter) . 'Adapter';
        $fqclass = 'ActiveRecord\\' . $class;
        $source = __DIR__ . "/adapters/$class.php";

        if (!file_exists($source)) {
            throw new DatabaseException("$fqclass not found!");
        }
        require_once $source;

        return $fqclass;
    }
    /**
     * Use this for any adapters that can take connection info in the form below
     * to set the adapters connection info.
     *
     * ```
     * protocol://username:password@host[:port]/dbname
     * protocol://urlencoded%20username:urlencoded%20password@host[:port]/dbname?decode=true
     * protocol://username:password@unix(/some/file/path)/dbname
     * ```
     *
     * Sqlite has a special syntax, as it does not need a database name or user authentication:
     *
     * ```
     * sqlite://file.db
     * sqlite://../relative/path/to/file.db
     * sqlite://unix(/absolute/path/to/file.db)
     * sqlite://windows(c%2A/absolute/path/to/file.db)
     * ```
     *
     * @param string $connection_url A connection URL
     *
     * @return object the parsed URL as an object
     */
    public static function parse_connection_url($connection_url)
    {
        $url = @parse_url($connection_url);

        if (!isset($url['host'])) {
            throw new DatabaseException('Database host must be specified in the connection string. If you want to specify an absolute filename, use e.g. sqlite://unix(/path/to/file)');
        }
        $info = new \stdClass();
        $info->protocol = $url['scheme'];
        $info->host = $url['host'];
        $info->db = isset($url['path']) ? substr($url['path'], 1) : null;
        $info->user = isset($url['user']) ? $url['user'] : null;
        $info->pass = isset($url['pass']) ? $url['pass'] : null;

        $allow_blank_db = ('sqlite' == $info->protocol);

        if ('unix(' == $info->host) {
            $socket_database = $info->host . '/' . $info->db;

            if ($allow_blank_db) {
                $unix_regex = '/^unix\((.+)\)\/?().*$/';
            } else {
                $unix_regex = '/^unix\((.+)\)\/(.+)$/';
            }

            if (preg_match_all($unix_regex, $socket_database, $matches) > 0) {
                $info->host = $matches[1][0];
                $info->db = $matches[2][0];
            }
        } elseif ('windows(' == substr($info->host, 0, 8)) {
            $info->host = urldecode(substr($info->host, 8) . '/' . substr($info->db, 0, -1));
            $info->db = null;
        }

        if ($allow_blank_db && $info->db) {
            $info->host .= '/' . $info->db;
        }

        if (isset($url['port'])) {
            $info->port = $url['port'];
        }

        if (false !== strpos($connection_url, 'decode=true')) {
            if ($info->user) {
                $info->user = urldecode($info->user);
            }

            if ($info->pass) {
                $info->pass = urldecode($info->pass);
            }
        }

        if (isset($url['query'])) {
            foreach (explode('/&/', $url['query']) as $pair) {
                list($name, $value) = explode('=', $pair);

                if ('charset' == $name) {
                    $info->charset = $value;
                }
            }
        }

        return $info;
    }
}