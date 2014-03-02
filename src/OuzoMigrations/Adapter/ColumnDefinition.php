<?php
namespace OuzoMigrations\Adapter;

use OuzoMigrations\OuzoMigrationsException;

class ColumnDefinition
{
    /**
     * @var Base
     */
    private $_adapter;

    public $name;

    public $type;

    public $properties;

    private $_options = array();

    public function __construct($adapter, $name, $type, $options = array())
    {
        if (!($adapter instanceof Base)) {
            throw new OuzoMigrationsException('Invalid Adapter instance.', OuzoMigrationsException::INVALID_ADAPTER);
        }
        if (empty($name) || !is_string($name)) {
            throw new OuzoMigrationsException("Invalid 'name' parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($type) || !is_string($type)) {
            throw new OuzoMigrationsException("Invalid 'type' parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }

        $this->_adapter = $adapter;
        $this->name = $name;
        $this->type = $type;
        $this->_options = $options;
    }

    public function to_sql()
    {
        $column_sql = sprintf("%s %s", $this->_adapter->identifier($this->name), $this->sql_type());
        $column_sql .= $this->_adapter->add_column_options($this->type, $this->_options);

        return $column_sql;
    }

    public function __toString()
    {
        return $this->to_sql();
    }

    private function sql_type()
    {
        return $this->_adapter->type_to_sql($this->type, $this->_options);
    }
}