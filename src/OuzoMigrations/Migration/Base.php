<?php
namespace OuzoMigrations\Migration;

use OuzoMigrations\OuzoMigrationsException;

class Base
{
    /**
     * @var \OuzoMigrations\Adapter\AdapterInterface
     */
    private $_adapter;

    public function __construct($adapter)
    {
        $this->set_adapter($adapter);
    }

    public function __call($name, $args)
    {
        throw new OuzoMigrationsException('Method unknown (' . $name . ')', OuzoMigrationsException::INVALID_MIGRATION_METHOD);
    }

    public function set_adapter($adapter)
    {
        if (!($adapter instanceof \OuzoMigrations\Adapter\AdapterBase)) {
            throw new OuzoMigrationsException('Adapter must be implement Base!', OuzoMigrationsException::INVALID_ADAPTER);
        }
        $this->_adapter = $adapter;
        return $this;
    }

    public function get_adapter()
    {
        return $this->_adapter;
    }

    public function create_database($name, $options = null)
    {
        return $this->_adapter->createDatabase($name, $options);
    }

    public function drop_database($name)
    {
        return $this->_adapter->dropDatabase($name);
    }

    public function drop_table($tbl)
    {
        return $this->_adapter->drop_table($tbl);
    }

    public function rename_table($name, $new_name)
    {
        return $this->_adapter->rename_table($name, $new_name);
    }

    public function rename_column($tbl_name, $column_name, $new_column_name)
    {
        return $this->_adapter->rename_column($tbl_name, $column_name, $new_column_name);
    }

    public function add_column($table_name, $column_name, $type, $options = array())
    {
        return $this->_adapter->add_column($table_name, $column_name, $type, $options);
    }

    public function remove_column($table_name, $column_name)
    {
        return $this->_adapter->remove_column($table_name, $column_name);
    }

    public function change_column($table_name, $column_name, $type, $options = array())
    {
        return $this->_adapter->change_column($table_name, $column_name, $type, $options);
    }

    public function add_index($table_name, $column_name, $options = array())
    {
        return $this->_adapter->add_index($table_name, $column_name, $options);
    }

    public function remove_index($table_name, $column_name, $options = array())
    {
        return $this->_adapter->remove_index($table_name, $column_name, $options);
    }

    public function create_table($table_name, $options = array())
    {
        return $this->_adapter->createTable($table_name, $options);
    }

    public function execute($query)
    {
        return $this->_adapter->query($query);
    }

    public function select_one($sql)
    {
        return $this->_adapter->selectOne($sql);
    }

    public function select_all($sql)
    {
        return $this->_adapter->select_all($sql);

    }

    public function query($sql)
    {
        return $this->_adapter->query($sql);
    }

    public function quote_string($str)
    {
        return $this->_adapter->quoteString($str);
    }
}