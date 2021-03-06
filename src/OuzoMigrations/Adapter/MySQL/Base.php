<?php
namespace OuzoMigrations\Adapter\MySQL;

use Exception;
use mysqli;
use OuzoMigrations\Adapter\AdapterInterface;
use OuzoMigrations\Adapter\ColumnDefinition;
use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Util\Naming;

define('MYSQL_MAX_IDENTIFIER_LENGTH', 64);

class AdapterBase extends \OuzoMigrations\Adapter\AdapterBase implements AdapterInterface
{
    public $db_info;

    public $conn;

    private $_tables = array();

    private $_tables_loaded = false;

    private $_version = '1.0';

    private $_in_trx = false;

    public function __construct($dsn, $logger)
    {
        parent::__construct($dsn);
        $this->connect($dsn);
        $this->set_logger($logger);
    }

    public function getDatabaseName()
    {
        return $this->db_info['database'];
    }

    public function supportsMigrations()
    {
        return true;
    }

    public function nativeDatabaseTypes()
    {
        return array(
            'primary_key' => array('name' => 'integer', 'limit' => 11, 'null' => false),
            'string' => array('name' => "varchar", 'limit' => 255),
            'text' => array('name' => "text"),
            'tinytext' => array('name' => "tinytext"),
            'mediumtext' => array('name' => 'mediumtext'),
            'integer' => array('name' => "int", 'limit' => 11),
            'tinyinteger' => array('name' => "tinyint"),
            'smallinteger' => array('name' => "smallint"),
            'mediuminteger' => array('name' => "mediumint"),
            'biginteger' => array('name' => "bigint"),
            'float' => array('name' => "float"),
            'decimal' => array('name' => "decimal", 'scale' => 0, 'precision' => 10),
            'datetime' => array('name' => "datetime"),
            'timestamp' => array('name' => "timestamp"),
            'time' => array('name' => "time"),
            'date' => array('name' => "date"),
            'binary' => array('name' => "blob"),
            'tinybinary' => array('name' => "tinyblob"),
            'mediumbinary' => array('name' => "mediumblob"),
            'longbinary' => array('name' => "longblob"),
            'boolean' => array('name' => "tinyint", 'limit' => 1),
            'enum' => array('name' => "enum", 'values' => array()),
            'uuid' => array('name' => "char", 'limit' => 36),
        );
    }

    public function start_transaction()
    {
        if (!$this->inTransaction()) {
            $this->beginTransaction();
        }
    }

    public function commit_transaction()
    {
        if ($this->inTransaction()) {
            $this->commit();
        }
    }

    public function rollbackTransaction()
    {
        if ($this->inTransaction()) {
            $this->rollback();
        }
    }

    private function inTransaction()
    {
        return $this->_in_trx;
    }

    private function beginTransaction()
    {
        if ($this->_in_trx === true) {
            throw new OuzoMigrationsException('Transaction already started', OuzoMigrationsException::QUERY_ERROR);
        }
        $this->conn->autocommit(FALSE);
        $this->_in_trx = true;
    }

    private function commit()
    {
        if ($this->_in_trx === false) {
            throw new OuzoMigrationsException('Transaction not started', OuzoMigrationsException::QUERY_ERROR);
        }
        $this->conn->commit();
        $this->_in_trx = false;
    }

    private function rollback()
    {
        if ($this->_in_trx === false) {
            throw new OuzoMigrationsException(
                'Transaction not started',
                OuzoMigrationsException::QUERY_ERROR
            );
        }
        $this->conn->rollback();
        $this->_in_trx = false;
    }

    public function quoteTable($str)
    {
        return "`" . $str . "`";
    }

    public function databaseExists($db)
    {
        $ddl = "SHOW DATABASES";
        $result = $this->selectAll($ddl);
        if (count($result) == 0) {
            return false;
        }
        foreach ($result as $dbrow) {
            if ($dbrow['Database'] == $db) {
                return true;
            }
        }
        return false;
    }

    public function createDatabase($db)
    {
        if ($this->databaseExists($db)) {
            return false;
        }
        $ddl = sprintf("CREATE DATABASE %s", $this->identifier($db));
        return $this->query($ddl);
    }

    public function dropDatabase($db)
    {
        if (!$this->databaseExists($db)) {
            return false;
        }
        $ddl = sprintf("DROP DATABASE IF EXISTS %s", $this->identifier($db));
        return $this->query($ddl);
    }

    public function schema($output_file)
    {
        $final = "";
        $views = '';
        $this->load_tables(true);
        foreach ($this->_tables as $tbl => $idx) {
            if ($tbl == 'schema_info') {
                continue;
            }
            $stmt = sprintf("SHOW CREATE TABLE %s", $this->identifier($tbl));
            $result = $this->query($stmt);

            if (is_array($result) && count($result) == 1) {
                $row = $result[0];
                if (count($row) == 2) {
                    if (isset($row['Create Table'])) {
                        $final .= $row['Create Table'] . ";\n\n";
                    } elseif (isset($row['Create View'])) {
                        $views .= $row['Create View'] . ";\n\n";
                    }
                }
            }
        }
        $data = $final . $views;
        return file_put_contents($output_file, $data, LOCK_EX);
    }

    public function tableExists($tbl, $reload_tables = false)
    {
        $this->load_tables($reload_tables);
        return array_key_exists($tbl, $this->_tables);
    }

    public function query($query)
    {
        $this->logger->log($query);
        $query_type = $this->determineQueryType($query);
        $data = array();
        if ($query_type == SQL_SELECT || $query_type == SQL_SHOW) {
            $res = $this->conn->query($query);
            if ($this->isError($res)) {
                throw new OuzoMigrationsException(sprintf("Error executing 'query' with:\n%s\n\nReason: %s\n\n", $query, $this->conn->error), OuzoMigrationsException::QUERY_ERROR);
            }
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            return $data;
        } else {
            $res = $this->conn->query($query);
            if ($this->isError($res)) {
                throw new OuzoMigrationsException(sprintf("Error executing 'query' with:\n%s\n\nReason: %s\n\n", $query, $this->conn->error), OuzoMigrationsException::QUERY_ERROR);
            }
            if ($query_type == SQL_INSERT) {
                return $this->conn->insert_id;
            }
            return true;
        }
    }

    public function selectOne($query)
    {
        $this->logger->log($query);
        $query_type = $this->determineQueryType($query);
        if ($query_type == SQL_SELECT || $query_type == SQL_SHOW) {
            $res = $this->conn->query($query);
            if ($this->isError($res)) {
                throw new OuzoMigrationsException(sprintf("Error executing 'query' with:\n%s\n\nReason: %s\n\n", $query, $this->conn->error), OuzoMigrationsException::QUERY_ERROR);
            }
            return $res->fetch_assoc();
        } else {
            throw new OuzoMigrationsException("Query for select_one() is not one of SELECT or SHOW: $query", OuzoMigrationsException::QUERY_ERROR);
        }
    }

    public function createTable($table_name, $options = array())
    {
        return new TableDefinition($this, $table_name, $options);
    }

    public function drop_table($tbl)
    {
        $ddl = sprintf("DROP TABLE IF EXISTS %s", $this->identifier($tbl));
        $this->query($ddl);
        return true;
    }

    public function quoteString($string)
    {
        return $this->conn->real_escape_string($string);
    }

    public function identifier($string)
    {
        return "`" . $string . "`";
    }

    public function quote($value)
    {
        return $this->quoteString($value);
    }

    public function rename_table($name, $new_name)
    {
        if (empty($name)) {
            throw new OuzoMigrationsException("Missing original column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($new_name)) {
            throw new OuzoMigrationsException("Missing new column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        $sql = sprintf("RENAME TABLE %s TO %s", $this->identifier($name), $this->identifier($new_name));
        return $this->executeDdl($sql);
    }

    public function add_column($table_name, $column_name, $type, $options = array())
    {
        if (empty($table_name)) {
            throw new OuzoMigrationsException("Missing table name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($column_name)) {
            throw new OuzoMigrationsException("Missing column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($type)) {
            throw new OuzoMigrationsException("Missing type parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }

        if (!array_key_exists('limit', $options)) {
            $options['limit'] = null;
        }
        if (!array_key_exists('precision', $options)) {
            $options['precision'] = null;
        }
        if (!array_key_exists('scale', $options)) {
            $options['scale'] = null;
        }
        $sql = sprintf("ALTER TABLE %s ADD `%s` %s", $this->identifier($table_name), $column_name, $this->typeToSql($type, $options));
        $sql .= $this->addColumnOptions($type, $options);
        return $this->executeDdl($sql);
    }

    public function remove_column($table_name, $column_name)
    {
        $sql = sprintf("ALTER TABLE %s DROP COLUMN %s", $this->identifier($table_name), $this->identifier($column_name));
        return $this->executeDdl($sql);
    }

    public function rename_column($table_name, $column_name, $new_column_name)
    {
        if (empty($table_name)) {
            throw new OuzoMigrationsException("Missing table name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($column_name)) {
            throw new OuzoMigrationsException("Missing original column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($new_column_name)) {
            throw new OuzoMigrationsException("Missing new column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        $column_info = $this->column_info($table_name, $column_name);
        $current_type = $column_info['type'];
        $sql = sprintf("ALTER TABLE %s CHANGE %s %s %s",
            $this->identifier($table_name),
            $this->identifier($column_name),
            $this->identifier($new_column_name), $current_type);

        $sql .= $this->addColumnOptions($current_type, $column_info);
        return $this->executeDdl($sql);
    }

    public function change_column($table_name, $column_name, $type, $options = array())
    {
        if (empty($table_name)) {
            throw new OuzoMigrationsException("Missing table name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($column_name)) {
            throw new OuzoMigrationsException("Missing original column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($type)) {
            throw new OuzoMigrationsException("Missing type parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }

        $this->column_info($table_name, $column_name);

        if (!array_key_exists('limit', $options)) {
            $options['limit'] = null;
        }
        if (!array_key_exists('precision', $options)) {
            $options['precision'] = null;
        }
        if (!array_key_exists('scale', $options)) {
            $options['scale'] = null;
        }
        $sql = sprintf("ALTER TABLE `%s` CHANGE `%s` `%s` %s", $table_name, $column_name, $column_name, $this->typeToSql($type, $options));
        $sql .= $this->addColumnOptions($type, $options);
        return $this->executeDdl($sql);
    }

    public function column_info($table, $column)
    {
        if (empty($table)) {
            throw new OuzoMigrationsException("Missing table name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($column)) {
            throw new OuzoMigrationsException("Missing original column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        try {
            $sql = sprintf("SHOW FULL COLUMNS FROM %s LIKE '%s'", $this->identifier($table), $column);
            $result = $this->selectOne($sql);
            if (is_array($result)) {
                $result = array_change_key_case($result, CASE_LOWER);
            }
            return $result;
        } catch (Exception $e) {
            return null;
        }
    }

    public function add_index($table_name, $column_name, $options = array())
    {
        if (empty($table_name)) {
            throw new OuzoMigrationsException("Missing table name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($column_name)) {
            throw new OuzoMigrationsException("Missing column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        //unique index?
        if (is_array($options) && array_key_exists('unique', $options) && $options['unique'] === true) {
            $unique = true;
        } else {
            $unique = false;
        }
        //did the user specify an index name?
        if (is_array($options) && array_key_exists('name', $options)) {
            $index_name = $options['name'];
        } else {
            $index_name = Naming::index_name($table_name, $column_name);
        }

        if (strlen($index_name) > MYSQL_MAX_IDENTIFIER_LENGTH) {
            $msg = "The auto-generated index name is too long for MySQL (max is 64 chars). ";
            $msg .= "Considering using 'name' option parameter to specify a custom name for this index.";
            $msg .= " Note: you will also need to specify";
            $msg .= " this custom name in a drop_index() - if you have one.";
            throw new OuzoMigrationsException($msg, OuzoMigrationsException::INVALID_INDEX_NAME);
        }
        if (!is_array($column_name)) {
            $column_names = array($column_name);
        } else {
            $column_names = $column_name;
        }
        $cols = array();
        foreach ($column_names as $name) {
            $cols[] = $this->identifier($name);
        }
        $sql = sprintf("CREATE %sINDEX %s ON %s(%s)",
            $unique ? "UNIQUE " : "",
            $index_name,
            $this->identifier($table_name),
            join(", ", $cols));

        return $this->executeDdl($sql);
    }

    public function remove_index($table_name, $column_name, $options = array())
    {
        if (empty($table_name)) {
            throw new OuzoMigrationsException("Missing table name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($column_name)) {
            throw new OuzoMigrationsException("Missing column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        //did the user specify an index name?
        if (is_array($options) && array_key_exists('name', $options)) {
            $index_name = $options['name'];
        } else {
            $index_name = Naming::index_name($table_name, $column_name);
        }
        $sql = sprintf("DROP INDEX %s ON %s", $this->identifier($index_name), $this->identifier($table_name));
        return $this->executeDdl($sql);
    }

    public function has_index($table_name, $column_name, $options = array())
    {
        if (empty($table_name)) {
            throw new OuzoMigrationsException("Missing table name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($column_name)) {
            throw new OuzoMigrationsException("Missing column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        //did the user specify an index name?
        if (is_array($options) && array_key_exists('name', $options)) {
            $index_name = $options['name'];
        } else {
            $index_name = Naming::index_name($table_name, $column_name);
        }
        $indexes = $this->indexes($table_name);
        foreach ($indexes as $idx) {
            if ($idx['name'] == $index_name) {
                return true;
            }
        }
        return false;
    }

    public function indexes($table_name)
    {
        $sql = sprintf("SHOW KEYS FROM %s", $this->identifier($table_name));
        $result = $this->selectAll($sql);
        $indexes = array();
        $cur_idx = null;
        foreach ($result as $row) {
            //skip primary
            if ($row['Key_name'] == 'PRIMARY') {
                continue;
            }
            $indexes[] = array('name' => $row['Key_name'], 'unique' => (int)$row['Non_unique'] == 0 ? true : false);
        }
        return $indexes;
    }

    public function typeToSql($type, $options = array())
    {
        $natives = $this->nativeDatabaseTypes();
        if (!array_key_exists($type, $natives)) {
            $error = sprintf("Error:I dont know what column type of '%s' maps to for MySQL.", $type);
            $error .= "\nYou provided: {$type}\n";
            $error .= "Valid types are: \n";
            $types = array_keys($natives);
            foreach ($types as $t) {
                if ($t == 'primary_key') {
                    continue;
                }
                $error .= "\t{$t}\n";
            }
            throw new OuzoMigrationsException($error, OuzoMigrationsException::INVALID_ARGUMENT);
        }

        $scale = null;
        $precision = null;
        $limit = null;

        if (isset($options['precision'])) {
            $precision = $options['precision'];
        }
        if (isset($options['scale'])) {
            $scale = $options['scale'];
        }
        if (isset($options['limit'])) {
            $limit = $options['limit'];
        }
        if (isset($options['values'])) {
            $values = $options['values'];
        }

        $native_type = $natives[$type];
        if (is_array($native_type) && array_key_exists('name', $native_type)) {
            $column_type_sql = $native_type['name'];
        } else {
            return $native_type;
        }
        if ($type == "decimal") {
            //ignore limit, use precison and scale
            if ($precision == null && array_key_exists('precision', $native_type)) {
                $precision = $native_type['precision'];
            }
            if ($scale == null && array_key_exists('scale', $native_type)) {
                $scale = $native_type['scale'];
            }
            if ($precision != null) {
                if (is_int($scale)) {
                    $column_type_sql .= sprintf("(%d, %d)", $precision, $scale);
                } else {
                    $column_type_sql .= sprintf("(%d)", $precision);
                }
                //scale
            } else {
                if ($scale) {
                    throw new OuzoMigrationsException("Error adding decimal column: precision cannot be empty if scale is specified", OuzoMigrationsException::INVALID_ARGUMENT);
                }
            }
            //precision
        } elseif ($type == "float") {
            //ignore limit, use precison and scale
            if ($precision == null && array_key_exists('precision', $native_type)) {
                $precision = $native_type['precision'];
            }
            if ($scale == null && array_key_exists('scale', $native_type)) {
                $scale = $native_type['scale'];
            }
            if ($precision != null) {
                if (is_int($scale)) {
                    $column_type_sql .= sprintf("(%d, %d)", $precision, $scale);
                } else {
                    $column_type_sql .= sprintf("(%d)", $precision);
                }
                //scale
            } else {
                if ($scale) {
                    throw new OuzoMigrationsException("Error adding float column: precision cannot be empty if scale is specified", OuzoMigrationsException::INVALID_ARGUMENT);
                }
            }
            //precision
        } elseif ($type == "enum") {
            if (empty($values)) {
                throw new OuzoMigrationsException("Error adding enum column: there must be at least one value defined", OuzoMigrationsException::INVALID_ARGUMENT);
            } else {
                $column_type_sql .= sprintf("('%s')", implode("','", array_map(array($this, 'quoteString'), $values)));
            }
        } else {
            //not a decimal column
            if ($limit == null && array_key_exists('limit', $native_type)) {
                $limit = $native_type['limit'];
            }
            if ($limit) {
                $column_type_sql .= sprintf("(%d)", $limit);
            }
        }

        return $column_type_sql;
    }

    public function primary_keys($table_name)
    {
        return array();
    }

    public function addColumnOptions($type, $options)
    {
        $sql = "";

        if (!is_array($options)) {
            return $sql;
        }

        if (array_key_exists('unsigned', $options) && $options['unsigned'] === true) {
            $sql .= ' UNSIGNED';
        }

        if (array_key_exists('character', $options)) {
            $sql .= sprintf(" CHARACTER SET %s", $this->identifier($options['character']));
        }
        if (array_key_exists('collate', $options)) {
            $sql .= sprintf(" COLLATE %s", $this->identifier($options['collate']));
        }

        if (array_key_exists('auto_increment', $options) && $options['auto_increment'] === true) {
            $sql .= ' auto_increment';
        }

        if (array_key_exists('default', $options) && $options['default'] !== null) {
            if ($this->is_sql_method_call($options['default'])) {
                //$default_value = $options['default'];
                throw new OuzoMigrationsException("MySQL does not support function calls as default values, constants only.", OuzoMigrationsException::INVALID_ARGUMENT);
            }

            if (is_int($options['default'])) {
                $default_format = '%d';
            } elseif (is_bool($options['default'])) {
                $default_format = "'%d'";
            } else {
                $default_format = "'%s'";
            }
            $default_value = sprintf($default_format, $options['default']);

            $sql .= sprintf(" DEFAULT %s", $default_value);
        }

        if (array_key_exists('null', $options) && ($options['null'] === false || $options['null'] === 'NO')) {
            $sql .= " NOT NULL";
        }
        if (array_key_exists('comment', $options)) {
            $sql .= sprintf(" COMMENT '%s'", $this->quoteString($options['comment']));
        }
        if (array_key_exists('after', $options)) {
            $sql .= sprintf(" AFTER %s", $this->identifier($options['after']));
        }
        return $sql;
    }

    public function setCurrentVersion($version)
    {
        $sql = sprintf("INSERT INTO %s (version) VALUES ('%s')", RUCKUSING_TS_SCHEMA_TBL_NAME, $version);
        return $this->executeDdl($sql);
    }

    public function remove_version($version)
    {
        $sql = sprintf("DELETE FROM %s WHERE version = '%s'", RUCKUSING_TS_SCHEMA_TBL_NAME, $version);
        return $this->executeDdl($sql);
    }

    public function __toString()
    {
        return "Base, version " . $this->_version;
    }

    private function connect($dsn)
    {
        $this->db_connect($dsn);
    }

    private function db_connect($dsn)
    {
        $db_info = $this->get_dsn();
        if ($db_info) {
            $this->db_info = $db_info;
            //we might have a port
            if (empty($db_info['port'])) {
                $db_info['port'] = 3306;
            }
            if (empty($db_info['socket'])) {
                $db_info['socket'] = @ini_get('mysqli.default_socket');
            }
            if (empty($db_info['charset'])) {
                $db_info['charset'] = "utf8";
            }
            $this->conn = new mysqli($db_info['host'], $db_info['user'], $db_info['password'], '', $db_info['port'], $db_info['socket']); //db name leaved for selection
            if ($this->conn->connect_error) {
                throw new OuzoMigrationsException("\n\nCould not connect to the DB, check host / user / password\n\n", OuzoMigrationsException::INVALID_CONFIG);
            }
            if (!$this->conn->select_db($db_info['database'])) {
                throw new OuzoMigrationsException("\n\nCould not select the DB " . $db_info['database'] . ", check permissions on host " . $db_info['host'] . " \n\n", OuzoMigrationsException::INVALID_CONFIG);
            }
            if (!$this->conn->set_charset($db_info['charset'])) {
                throw new OuzoMigrationsException("\n\nCould not set charset " . $db_info['charset'] . " \n\n", OuzoMigrationsException::INVALID_CONFIG);
            }
            return true;
        } else {
            throw new OuzoMigrationsException("\n\nCould not extract DB connection information from: " . implode(' ', $dsn) . "\n\n", OuzoMigrationsException::INVALID_CONFIG);
        }
    }

    private function isError($o)
    {
        return $o === FALSE;
    }

    private function load_tables($reload = true)
    {
        if ($this->_tables_loaded == false || $reload) {
            $this->_tables = array(); //clear existing structure
            $query = "SHOW TABLES";
            $res = $this->conn->query($query);
            while ($row = $res->fetch_row()) {
                $table = $row[0];
                $this->_tables[$table] = true;
            }
        }
    }

    private function is_sql_method_call($str)
    {
        $str = trim($str);
        return substr($str, -2, 2) == "()";
    }
}