<?php
namespace OuzoMigrations\Adapter\MySQL;

use OuzoMigrations\Adapter\ColumnDefinition;
use OuzoMigrations\OuzoMigrationsException;

class TableDefinition
{
    /**
     * @var AdapterBase
     */
    private $_adapter;

    private $_name;

    private $_options;

    private $_sql = "";

    private $_initialized = false;

    private $_columns = array();

    private $_table_def;

    private $_primary_keys = array();

    private $_auto_generate_id = true;

    public function __construct($adapter, $name, $options = array())
    {
        if (!($adapter instanceof AdapterBase)) {
            throw new OuzoMigrationsException("Invalid MySQL Adapter instance.", OuzoMigrationsException::INVALID_ADAPTER);
        }
        if (!$name) {
            throw new OuzoMigrationsException("Invalid 'name' parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }

        $this->_adapter = $adapter;
        $this->_name = $name;
        $this->_options = $options;
        $this->init_sql($name, $options);
        $this->_table_def = new \OuzoMigrations\Adapter\TableDefinitionBase($this->_adapter, $this->_options);

        if (array_key_exists('id', $options)) {
            if (is_bool($options['id']) && $options['id'] == false) {
                $this->_auto_generate_id = false;
            }
            //if its a string then we want to auto-generate an integer-based
            //primary key with this name
            if (is_string($options['id'])) {
                $this->_auto_generate_id = true;
                $this->_primary_keys[] = $options['id'];
            }
        }
    }

    public function column($column_name, $type, $options = array())
    {
        //if there is already a column by the same name then silently fail
        //and continue
        if ($this->_table_def->included($column_name) == true) {
            return;
        }

        $column_options = array();

        if (array_key_exists('primary_key', $options)) {
            if ($options['primary_key'] == true) {
                $this->_primary_keys[] = $column_name;
            }
        }

        if (array_key_exists('auto_increment', $options)) {
            if ($options['auto_increment'] == true) {
                $column_options['auto_increment'] = true;
            }
        }
        $column_options = array_merge($column_options, $options);
        $column = new ColumnDefinition($this->_adapter, $column_name, $type, $column_options);

        $this->_columns[] = $column;
    }

    private function keys()
    {
        if (count($this->_primary_keys) > 0) {
            $lead = ' PRIMARY KEY (';
            $quoted = array();
            foreach ($this->_primary_keys as $key) {
                $quoted[] = sprintf("%s", $this->_adapter->identifier($key));
            }
            $primary_key_sql = ",\n" . $lead . implode(",", $quoted) . ")";

            return ($primary_key_sql);
        } else {
            return '';
        }
    }

    public function finish($wants_sql = false)
    {
        if ($this->_initialized == false) {
            throw new OuzoMigrationsException(sprintf("Table Definition: '%s' has not been initialized", $this->_name), OuzoMigrationsException::INVALID_TABLE_DEFINITION);
        }
        if (is_array($this->_options) && array_key_exists('options', $this->_options)) {
            $opt_str = $this->_options['options'];
        } else {
            $opt_str = null;
        }
        if (isset($this->_adapter->db_info['charset'])) {
            $opt_str .= " DEFAULT CHARSET=" . $this->_adapter->db_info['charset'];
        } else {
            $opt_str .= " DEFAULT CHARSET=latin1";
        }

        $close_sql = sprintf(") %s;", $opt_str);
        $create_table_sql = $this->_sql;

        if ($this->_auto_generate_id === true) {
            $this->_primary_keys[] = 'id';
            $primary_id = new ColumnDefinition($this->_adapter, 'id', 'integer', array('unsigned' => true, 'null' => false, 'auto_increment' => true));
            $create_table_sql .= $primary_id->toSql() . ",\n";
        }

        $create_table_sql .= $this->columns_to_str();
        $create_table_sql .= $this->keys() . $close_sql;

        if ($wants_sql) {
            return $create_table_sql;
        } else {
            return $this->_adapter->executeDdl($create_table_sql);
        }
    }

    private function columns_to_str()
    {
        $fields = array();
        $len = count($this->_columns);
        for ($i = 0; $i < $len; $i++) {
            $c = $this->_columns[$i];
            $fields[] = $c->__toString();
        }
        return join(",\n", $fields);
    }

    private function init_sql($name, $options)
    {
        //are we forcing table creation? If so, drop it first
        if (array_key_exists('force', $options) && $options['force'] == true) {
            try {
                $this->_adapter->drop_table($name);
            } catch (OuzoMigrationsException $e) {
                if ($e->getCode() != OuzoMigrationsException::MISSING_TABLE) {
                    throw $e;
                }
            }
        }
        $temp = "";
        if (array_key_exists('temporary', $options)) {
            $temp = " TEMPORARY";
        }
        $create_sql = sprintf("CREATE%s TABLE ", $temp);
        $create_sql .= sprintf("%s (\n", $this->_adapter->identifier($name));
        $this->_sql .= $create_sql;
        $this->_initialized = true;
    }
}