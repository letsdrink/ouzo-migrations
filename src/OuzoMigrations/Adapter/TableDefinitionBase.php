<?php
namespace OuzoMigrations\Adapter;

use OuzoMigrations\OuzoMigrationsException;

class TableDefinitionBase
{
    private $_columns = array();

    /**
     * @var AdapterBase
     */
    private $_adapter;

    public function __construct($adapter)
    {
        if (!($adapter instanceof AdapterBase)) {
            throw new OuzoMigrationsException('Invalid Adapter instance.', OuzoMigrationsException::INVALID_ADAPTER);
        }
        $this->_adapter = $adapter;
    }

    public function __call($name, $args)
    {
        throw new OuzoMigrationsException('Method unknown (' . $name . ')', OuzoMigrationsException::INVALID_MIGRATION_METHOD);
    }

    public function included($column)
    {
        $k = count($this->_columns);
        for ($i = 0; $i < $k; $i++) {
            $col = $this->_columns[$i];
            if (is_string($column) && $col->name == $column) {
                return true;
            }
            if (($column instanceof ColumnDefinition) && $col->name == $column->name) {
                return true;
            }
        }

        return false;
    }

    public function to_sql()
    {
        return join(",", $this->_columns);
    }
}