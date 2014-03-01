<?php
namespace Ruckusing\Adapter;

class ColumnDefinition
{
    /**
     * adapter
     *
     * @var Base
     */

    private $_adapter;

    /**
     * name
     *
     * @var string
     */
    public $name;

    /**
     * type
     *
     * @var mixed
     */
    public $type;

    /**
     * properties
     *
     * @var mixed
     */
    public $properties;

    /**
     * options
     *
     * @var array
     */
    private $_options = array();

    /**
     * Creates an instance of ColumnDefinition
     *
     * @param Base $adapter The current adapter
     * @param string $name the name of the column
     * @param string $type the type of the column
     * @param array $options the column options
     *
     * @return ColumnDefinition
     */
    public function __construct($adapter, $name, $type, $options = array())
    {
        if (!($adapter instanceof Base)) {
            throw new RuckusingException(
                'Invalid Adapter instance.',
                RuckusingException::INVALID_ADAPTER
            );
        }
        if (empty($name) || !is_string($name)) {
            throw new RuckusingException(
                "Invalid 'name' parameter",
                RuckusingException::INVALID_ARGUMENT
            );
        }
        if (empty($type) || !is_string($type)) {
            throw new RuckusingException(
                "Invalid 'type' parameter",
                RuckusingException::INVALID_ARGUMENT
            );
        }

        $this->_adapter = $adapter;
        $this->name = $name;
        $this->type = $type;
        $this->_options = $options;
    }

    /**
     * sql version
     *
     * @return string
     */
    public function to_sql()
    {
        $column_sql = sprintf("%s %s", $this->_adapter->identifier($this->name), $this->sql_type());
        $column_sql .= $this->_adapter->add_column_options($this->type, $this->_options);

        return $column_sql;
    }

    /**
     * sql string version
     *
     * @return string
     */
    public function __toString()
    {
        return $this->to_sql();
    }

    /**
     * sql version
     *
     * @return string
     */
    private function sql_type()
    {
        return $this->_adapter->type_to_sql($this->type, $this->_options);
    }
}
