<?php
namespace OuzoMigrations\Adapter\PgSQL;

use Ouzo\Utilities\Arrays;
use OuzoMigrations\Adapter\AdapterBase;
use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Util\Naming;
use PDOStatement;

class AdapterPgSQL extends AdapterBase
{
    const PG_MAX_IDENTIFIER_LENGTH = 64;

    public $db_info;
    public $conn;

    private $_tables = array();
    private $_version = '1.0';

    public function createTable($tableName, array $options = array())
    {
        return new TableDefinitionPgSQL($this, $tableName, $options);
    }

    public function tableExists($tableName)
    {
        $this->_loadTables();
        return Arrays::keyExists($this->_tables, $tableName);
    }

    private function _loadTables()
    {
        $this->_tables = array();
        $sql = "SELECT tablename FROM pg_tables WHERE schemaname = ANY (current_schemas(false))";
        foreach ($this->_dbHandle->query($sql) as $row) {
            $table = $row['tablename'];
            $this->_tables[$table] = true;
        }
    }

    public function supportsMigrations()
    {
        return true;
    }

    public function nativeDatabaseTypes()
    {
        return array(
            'primary_key' => array('name' => 'serial'),
            'string' => array('name' => 'varchar', 'limit' => 255),
            'text' => array('name' => 'text'),
            'tinytext' => array('name' => 'text'),
            'mediumtext' => array('name' => 'text'),
            'integer' => array('name' => 'integer'),
            'tinyinteger' => array('name' => 'smallint'),
            'smallinteger' => array('name' => 'smallint'),
            'mediuminteger' => array('name' => 'integer'),
            'biginteger' => array('name' => 'bigint'),
            'float' => array('name' => 'float'),
            'decimal' => array('name' => 'decimal', 'scale' => 0, 'precision' => 10),
            'datetime' => array('name' => 'timestamp'),
            'timestamp' => array('name' => 'timestamp'),
            'time' => array('name' => 'time'),
            'date' => array('name' => 'date'),
            'binary' => array('name' => 'bytea'),
            'tinybinary' => array('name' => "bytea"),
            'mediumbinary' => array('name' => "bytea"),
            'longbinary' => array('name' => "bytea"),
            'boolean' => array('name' => 'boolean'),
            'tsvector' => array('name' => 'tsvector'),
            'uuid' => array('name' => 'uuid'),
        );
    }

    public function quoteTable($string)
    {
        return '"' . $string . '"';
    }

    public function quoteString($string)
    {
        return pg_escape_string($string);
    }

    public function quote($value)
    {
        $type = gettype($value);
        if ($type == "double") {
            return ("'{$value}'");
        } elseif ($type == "integer") {
            return ("'{$value}'");
        } else {
            // TODO: this global else is probably going to be problematic.
            // I think eventually we'll need to do more introspection and handle all possible types
            return ("'{$value}'");
        }
    }

    public function quoteColumnName($column)
    {
        return '"' . $column . '"';
    }

    public function databaseExists($db)
    {
        $sql = sprintf("SELECT datname FROM pg_database WHERE datname = '%s'", $db);
        $result = $this->selectOne($sql);
        return (count($result) == 1 && $result['datname'] == $db);
    }

    public function createDatabase($db, $options = array())
    {
        $was_in_transaction = false;
        if ($this->inTransaction()) {
            $this->commitTransaction();
            $was_in_transaction = true;
        }

        if (!array_key_exists('encoding', $options)) {
            $options['encoding'] = 'utf8';
        }
        $ddl = sprintf("CREATE DATABASE %s", $this->identifier($db));
        if (array_key_exists('owner', $options)) {
            $ddl .= " OWNER = \"{$options['owner']}\"";
        }
        if (array_key_exists('template', $options)) {
            $ddl .= " TEMPLATE = \"{$options['template']}\"";
        }
        if (array_key_exists('encoding', $options)) {
            $ddl .= " ENCODING = '{$options['encoding']}'";
        }
        if (array_key_exists('tablespace', $options)) {
            $ddl .= " TABLESPACE = \"{$options['tablespace']}\"";
        }
        if (array_key_exists('connection_limit', $options)) {
            $connlimit = intval($options['connection_limit']);
            $ddl .= " CONNECTION LIMIT = {$connlimit}";
        }
        $result = $this->query($ddl);

        if ($was_in_transaction) {
            $this->beginTransaction();
            $was_in_transaction = false;
        }

        return $result;
    }

    public function dropDatabase($db)
    {
        if (!$this->databaseExists($db)) {
            return false;
        }
        $ddl = sprintf("DROP DATABASE IF EXISTS %s", $this->quoteTable($db));
        $result = $this->query($ddl);
        return $result;
    }

    public function identifier($string)
    {
        return '"' . $string . '"';
    }

    public function typeToSql($type, $options = array())
    {
        $natives = $this->nativeDatabaseTypes();
        if (!array_key_exists($type, $natives)) {
            $error = sprintf("Error: I dont know what column type of '%s' maps to for Postgres.", $type);
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
            //pre
        }
        // integer columns dont support limit (sizing)
        if ($native_type['name'] != "integer") {
            if ($limit == null && array_key_exists('limit', $native_type)) {
                $limit = $native_type['limit'];
            }
            if ($limit) {
                $column_type_sql .= sprintf("(%d)", $limit);
            }
        }

        return $column_type_sql;
    }

    public function addColumnOptions($type, $options)
    {
        $sql = "";
        if (!is_array($options)) {
            return $sql;
        }
        if (array_key_exists('default', $options) && $options['default'] !== null) {
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

        if (Arrays::getValue($options, 'primary_key', false)) {
            $sql .= " primary key";
        }

        if (array_key_exists('null', $options) && $options['null'] === false) {
            $sql .= " NOT NULL";
        }
        return $sql;
    }

    public function query($query)
    {
        $query_type = $this->determineQueryType($query);
        $data = array();
        if ($query_type == AdapterBase::SQL_SELECT || $query_type == AdapterBase::SQL_SHOW) {
            $result = $this->_dbHandle->query($query);
            if (!$result) {
                $this->_throwQueryException($result, $query);
            }
            foreach ($result as $row) {
                $data[] = $row;
            }
            return $data;
        } else {
            $result = $this->_dbHandle->query($query);
            if (!$result) {
                $this->_throwQueryException($result, $query);
            }
            $returning_regex = '/ RETURNING \"(.+)\"$/';
            $matches = array();
            if (preg_match($returning_regex, $query, $matches)) {
                if (count($matches) == 2) {
                    $returning_column_value = pg_fetch_result($result, 0, $matches[1]);
                    return ($returning_column_value);
                }
            }
            return true;
        }
    }

    private function _throwQueryException(PDOStatement $result, $query)
    {
        throw new OuzoMigrationsException("Error executing 'query' with:\n " . $query . "\n\n Reason: " . $result->errorInfo(), OuzoMigrationsException::QUERY_ERROR);
    }

    public function selectOne($query)
    {
        $query_type = $this->determineQueryType($query);
        if ($query_type == AdapterBase::SQL_SELECT || $query_type == AdapterBase::SQL_SHOW) {
            $result = $this->_dbHandle->query($query);
            if (!$result) {
                $this->_throwQueryException($result, $query);
            }
            return $result->fetch();
        } else {
            throw new OuzoMigrationsException("Query for select_one() is not one of SELECT or SHOW: $query", OuzoMigrationsException::QUERY_ERROR);
        }
    }

    public function pk_and_sequence_for($table)
    {
        $sql = <<<SQL
      SELECT attr.attname, seq.relname
      FROM pg_class      seq,
           pg_attribute  attr,
           pg_depend     dep,
           pg_namespace  name,
           pg_constraint cons
      WHERE seq.oid           = dep.objid
        AND seq.relkind       = 'S'
        AND attr.attrelid     = dep.refobjid
        AND attr.attnum       = dep.refobjsubid
        AND attr.attrelid     = cons.conrelid
        AND attr.attnum       = cons.conkey[1]
        AND cons.contype      = 'p'
        AND dep.refobjid      = '%s'::regclass
SQL;
        $sql = sprintf($sql, $table);
        $result = $this->selectOne($sql);
        if ($result) {
            return (array($result['attname'], $result['re            $res = pg_query($this->conn, $query);
lname']));
        } else {
            return array();
        }
    }

    public function schema($output_file)
    {
        $command = sprintf("pg_dump -U %s -Fp -s -f '%s' %s",
            $this->db_info['user'],
            $output_file,
            $this->db_info['database']
        );
        return system($command);
    }

    public function drop_table($tbl)
    {
        $ddl = sprintf("DROP TABLE IF EXISTS %s", $this->quoteTable($tbl));
        $this->query($ddl);
        return true;
    }

    public function rename_table($name, $new_name)
    {
        if (empty($name)) {
            throw new OuzoMigrationsException("Missing original column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($new_name)) {
            throw new OuzoMigrationsException("Missing new column name parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        $sql = sprintf("ALTER TABLE %s RENAME TO %s", $this->identifier($name), $this->identifier($new_name));
        $this->executeDdl($sql);
        $pk_and_sequence_for = $this->pk_and_sequence_for($new_name);
        if (!empty($pk_and_sequence_for)) {
            list($pk, $seq) = $pk_and_sequence_for;
            if ($seq == "{$name}_{$pk}_seq") {
                $new_seq = "{$new_name}_{$pk}_seq";
                $this->executeDdl("ALTER TABLE $seq RENAME TO $new_seq");
            }
        }
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
        //default types
        if (!array_key_exists('limit', $options)) {
            $options['limit'] = null;
        }
        if (!array_key_exists('precision', $options)) {
            $options['precision'] = null;
        }
        if (!array_key_exists('scale', $options)) {
            $options['scale'] = null;
        }
        $sql = sprintf("ALTER TABLE %s ADD COLUMN %s %s",
            $this->quoteTable($table_name),
            $this->quoteColumnName($column_name),
            $this->typeToSql($type, $options)
        );
        $sql .= $this->addColumnOptions($type, $options);

        return $this->executeDdl($sql);
    }

    public function remove_column($table_name, $column_name)
    {
        $sql = sprintf("ALTER TABLE %s DROP COLUMN %s",
            $this->quoteTable($table_name),
            $this->quoteColumnName($column_name)
        );
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
        $column_info['type'];
        $sql = sprintf("ALTER TABLE %s RENAME COLUMN %s TO %s",
            $this->quoteTable($table_name),
            $this->quoteColumnName($column_name),
            $this->quoteColumnName($new_column_name)
        );
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

        //default types
        if (!array_key_exists('limit', $options)) {
            $options['limit'] = null;
        }
        if (!array_key_exists('precision', $options)) {
            $options['precision'] = null;
        }
        if (!array_key_exists('scale', $options)) {
            $options['scale'] = null;
        }
        $sql = sprintf("ALTER TABLE %s ALTER COLUMN %s TYPE %s",
            $this->quoteTable($table_name),
            $this->quoteColumnName($column_name),
            $this->typeToSql($type, $options)
        );
        $sql .= $this->addColumnOptions($type, $options);

        if (array_key_exists('default', $options)) {
            $this->change_column_default($table_name, $column_name, $options['default']);
        }
        if (array_key_exists('null', $options)) {
            $default = array_key_exists('default', $options) ? $options['default'] : null;
            $this->change_column_null($table_name, $column_name, $options['null'], $default);
        }
        return $this->executeDdl($sql);
    }

    private function change_column_default($table_name, $column_name, $default)
    {
        $sql = sprintf("ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s",
            $this->quoteTable($table_name),
            $this->quoteColumnName($column_name),
            $this->quote($default)
        );
        $this->executeDdl($sql);
    }

    private function change_column_null($table_name, $column_name, $null, $default = null)
    {
        if (($null !== false) || ($default !== null)) {
            $sql = sprintf("UPDATE %s SET %s=%s WHERE %s IS NULL",
                $this->quoteTable($table_name),
                $this->quoteColumnName($column_name),
                $this->quote($default),
                $this->quoteColumnName($column_name)
            );
            $this->query($sql);
        }
        $sql = sprintf("ALTER TABLE %s ALTER %s %s NOT NULL",
            $this->quoteTable($table_name),
            $this->quoteColumnName($column_name),
            ($null ? 'DROP' : 'SET')
        );
        $this->query($sql);
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
            $sql = <<<SQL
      SELECT a.attname, format_type(a.atttypid, a.atttypmod), d.adsrc, a.attnotnull
        FROM pg_attribute a LEFT JOIN pg_attrdef d
          ON a.attrelid = d.adrelid AND a.attnum = d.adnum
       WHERE a.attrelid = '%s'::regclass
         AND a.attname = '%s'
         AND a.attnum > 0 AND NOT a.attisdropped
       ORDER BY a.attnum
SQL;
            $sql = sprintf($sql, $this->quoteTable($table), $column);
            $result = $this->selectOne($sql);
            $data = array();
            if (is_array($result)) {
                $data['type'] = $result['format_type'];
                $data['name'] = $column;
                $data['field'] = $column;
                $data['null'] = $result['attnotnull'] == 'f';
                $data['default'] = $result['adsrc'];
            }

            return $data;
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

        if (strlen($index_name) > self::PG_MAX_IDENTIFIER_LENGTH) {
            $msg = "The auto-generated index name is too long for Postgres (max is 64 chars). ";
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
            $cols[] = $this->quoteColumnName($name);
        }
        $sql = sprintf("CREATE %sINDEX %s ON %s(%s)",
            $unique ? "UNIQUE " : "",
            $this->quoteColumnName($index_name),
            $this->quoteColumnName($table_name),
            join(", ", $cols)
        );

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
        $sql = sprintf("DROP INDEX %s", $this->quoteColumnName($index_name));

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
        $sql = <<<SQL
       SELECT distinct i.relname, d.indisunique, d.indkey, pg_get_indexdef(d.indexrelid), t.oid
       FROM pg_class t
       INNER JOIN pg_index d ON t.oid = d.indrelid
       INNER JOIN pg_class i ON d.indexrelid = i.oid
       WHERE i.relkind = 'i'
         AND d.indisprimary = 'f'
         AND t.relname = '%s'
         AND i.relnamespace IN (SELECT oid FROM pg_namespace WHERE nspname = ANY (current_schemas(false)) )
      ORDER BY i.relname
SQL;
        $sql = sprintf($sql, $table_name);
        $result = $this->selectAll($sql);

        $indexes = array();
        foreach ($result as $row) {
            $indexes[] = array(
                'name' => $row['relname'],
                'unique' => $row['indisunique'] == 't' ? true : false
            );
        }

        return $indexes;
    }

    public function primary_keys($table_name)
    {
        $sql = <<<SQL
      SELECT
        pg_attribute.attname,
        format_type(pg_attribute.atttypid, pg_attribute.atttypmod)
      FROM pg_index, pg_class, pg_attribute
      WHERE
        pg_class.oid = '%s'::regclass AND
        indrelid = pg_class.oid AND
        pg_attribute.attrelid = pg_class.oid AND
        pg_attribute.attnum = any(pg_index.indkey)
        AND indisprimary
SQL;
        $sql = sprintf($sql, $table_name);
        $result = $this->selectAll($sql);

        $primary_keys = array();
        foreach ($result as $row) {
            $primary_keys[] = array(
                'name' => $row['attname'],
                'type' => $row['format_type']
            );
        }

        return $primary_keys;
    }

    public function set_current_version($version)
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

    private function isError($o)
    {
        return $o === FALSE;
    }
}