<?php
namespace OuzoMigrations\Adapter;

use OuzoMigrations\OuzoMigrationsException;

class ColumnDefinition
{
    /**
     * @var AdapterBase
     */
    private $_adapter;
    private $_options = array();

    public $name;
    public $type;
    public $properties;

    public function __construct(AdapterBase $adapter, $name, $type, $options = array())
    {
        if (empty($name) || !is_string($name)) {
            throw new OuzoMigrationsException("Invalid 'name' parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (empty($type) || !is_string($type)) {
            throw new OuzoMigrationsException("Invalid 'type' parameter", OuzoMigrationsException::INVALID_ARGUMENT);
        }

        $this->_adapter = $adapter;
        $this->_options = $options;
        $this->name = $name;
        $this->type = $type;
    }

    public function toSql()
    {
        $sql = sprintf("%s %s", $this->_adapter->identifier($this->name), $this->_sqlType());
        $sql .= $this->_adapter->addColumnOptions($this->type, $this->_options);
        return $sql;
    }

    private function _sqlType()
    {
        return $this->_adapter->typeToSql($this->type, $this->_options);
    }

    public function __toString()
    {
        return $this->toSql();
    }
}