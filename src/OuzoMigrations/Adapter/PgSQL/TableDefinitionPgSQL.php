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
    /**
     * @var ColumnDefinition[]
     */
    private $_columns = array();
    private $_dll = '';
    private $_name;
    private $_options;

    public function __construct(AdapterPgSQL $adapter, $name, $options = array())
    {
        if (!$name) {
            throw new OuzoMigrationsException("Invalid 'name' parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }

        $this->_adapter = $adapter;
        $this->_name = $name;
        $this->_options = $options;

        $this->_initSql();
    }

    private function _initSql()
    {
        $this->_dll .= "CREATE TABLE " . $this->_adapter->quoteTable($this->_name) . " (";
    }

    private function _parseOptions()
    {
        if ($this->_addAutoIdField()) {
            $this->_dll .= 'id serial primary key, ';
        }
    }

    private function _addAutoIdField()
    {
        return Arrays::getValue($this->_options, 'id', true);
    }

    private function _createColumnsDll()
    {
        $columns = array();
        foreach ($this->_columns as $column) {
            $columns[] = $column->__toString();
        }
        $this->_dll .= implode(', ', $columns);
    }

    private function _closeBracket()
    {
        $this->_dll = rtrim($this->_dll, ', ');
        $this->_dll .= ')';
    }

    private function _addTableOptions()
    {
        $options = Arrays::getValue($this->_options, 'options');
        if ($options) {
            $this->_dll .= ' ' . $options;
        }
    }

    public function getDll()
    {
        $this->_parseOptions();
        $this->_createColumnsDll();
        $this->_closeBracket();
        $this->_addTableOptions();
        return $this->_dll;
    }

    public function column($columnName, $type, $options = array())
    {
        $this->_columns[] = new ColumnDefinition($this->_adapter, $columnName, $type, $options);
        return $this;
    }

    public function finish()
    {
        return $this->_adapter->executeDdl($this->getDll());
    }
}