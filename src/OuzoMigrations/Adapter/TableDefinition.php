<?php
namespace OuzoMigrations\Adapter;

use OuzoMigrations\RuckusingException;

class TableDefinition
{
    private $_columns = array();

    /**
     * @var Base
     */
    private $_adapter;

    public function __construct($adapter)
    {
        if (!($adapter instanceof Base)) {
            throw new RuckusingException('Invalid Adapter instance.', RuckusingException::INVALID_ADAPTER);
        }
        $this->_adapter = $adapter;
    }

    public function __call($name, $args)
    {
        throw new RuckusingException('Method unknown (' . $name . ')', RuckusingException::INVALID_MIGRATION_METHOD);
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