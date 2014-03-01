<?php

/**
 * Ruckusing
 *
 * @category  Ruckusing
 * @package   Ruckusing_Adapter
 * @author    Cody Caughlan <codycaughlan % gmail . com>
 * @link      https://github.com/ruckus/ruckusing-migrations
 */

/**
 * Ruckusing_Adapter_TableDefinition
 *
 * @category Ruckusing
 * @package  Ruckusing_Adapter
 * @author   Cody Caughlan <codycaughlan % gmail . com>
 * @link      https://github.com/ruckus/ruckusing-migrations
 */
class Ruckusing_Adapter_TableDefinition
{
    /**
     * columns
     *
     * @var array
     */
    private $_columns = array();

    /**
     * adapter
     *
     * @var Base
     */
    private $_adapter;

    /**
     * Creates an instance of Ruckusing_Adapter_TableDefinition
     *
     * @param Base $adapter the current adapter
     *
     * @return Ruckusing_Adapter_TableDefinition
     */
    public function __construct($adapter)
    {
        if (!($adapter instanceof Base)) {
            throw new RuckusingException(
                    'Invalid Adapter instance.',
                    RuckusingException::INVALID_ADAPTER
            );
        }
        $this->_adapter = $adapter;
    }

    /**
     * __call
     *
     * @param string $name The method name
     * @param array  $args The parameters of method called
     *
     * @throws RuckusingException
     */
    public function __call($name, $args)
    {
        throw new RuckusingException(
                'Method unknown (' . $name . ')',
                RuckusingException::INVALID_MIGRATION_METHOD
        );
    }

    /**
     * Determine whether or not the given column already exists in our
     * table definition.
     *
     * This method is lax enough that it can take either a string column name
     * or a ColumnDefinition object.
     *
     * @param string $_column the name of the column
     *
     * @return boolean
     */
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

    /**
     * Get list of columns
     *
     * @return string
     */
    public function to_sql()
    {
        return join(",", $this->_columns);
    }
}
