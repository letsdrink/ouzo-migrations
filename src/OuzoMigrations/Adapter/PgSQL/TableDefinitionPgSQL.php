<?php
namespace OuzoMigrations\Adapter\PgSQL;

use Ouzo\Utilities\Arrays;
use OuzoMigrations\Adapter\ColumnDefinition;
use OuzoMigrations\Adapter\TableDefinitionBase;
use OuzoMigrations\OuzoMigrationsException;

class TableDefinitionPgSQL extends TableDefinitionBase
{
    /**
     * @var AdapterPgSQL
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

    private $_dll = '';

    public function __construct(AdapterPgSQL $adapter, $name, $options = array())
    {
        if (!$name) {
            throw new OuzoMigrationsException("Invalid 'name' parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }

        $this->_adapter = $adapter;
        $this->_name = $name;
        $this->_options = $options;

        $this->_initSql();
//        $this->_table_def = new TableDefinitionBase($this->_adapter, $this->_options);
//
//        if (array_key_exists('id', $options)) {
//            if (is_bool($options['id']) && $options['id'] == false) {
//                $this->_auto_generate_id = false;
//            }
//            if (is_string($options['id'])) {
//                $this->_auto_generate_id = true;
//                $this->_primary_keys[] = $options['id'];
//            }
//        }
    }

    private function _initSql()
    {
        $this->_dll .= "CREATE TABLE " . $this->_adapter->quoteTable($this->_name) . " (";
    }

    private function _parseOptions()
    {
        if ($this->_addAutoIdField()) {
            $this->_dll .= 'id serial primary key';
        }
    }

    private function _addAutoIdField()
    {
        return Arrays::getValue($this->_options, 'id', true);
    }

    private function _closeBracket()
    {
        $this->_dll .= ')';
    }

    public function getDll()
    {
        $this->_parseOptions();
        $this->_closeBracket();
        return $this->_dll;
    }

    public function column($column_name, $type, $options = array())
    {
        if ($this->_table_def->included($column_name) == true) {
            return;
        }

        $column_options = array();

        if (array_key_exists('primary_key', $options)) {
            if ($options['primary_key'] == true) {
                $this->_primary_keys[] = $column_name;
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

        $close_sql = sprintf(") %s;", $opt_str);
        $create_table_sql = $this->_sql;

        if ($this->_auto_generate_id === true) {
            $this->_primary_keys[] = 'id';
            $primary_id = new ColumnDefinition($this->_adapter, 'id', 'primary_key');
            $create_table_sql .= $primary_id->to_sql() . ",\n";
        }

        $create_table_sql .= $this->columns_to_str();
        $create_table_sql .= $this->keys() . $close_sql;

        if ($wants_sql) {
            return $create_table_sql;
        } else {
            return $this->_adapter->execute_ddl($create_table_sql);
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
}