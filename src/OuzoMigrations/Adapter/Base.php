<?php
namespace OuzoMigrations\Adapter;

use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Util\Logger;

define('SQL_UNKNOWN_QUERY_TYPE', 1);
define('SQL_SELECT', 2);
define('SQL_INSERT', 4);
define('SQL_UPDATE', 8);
define('SQL_DELETE', 16);
define('SQL_ALTER', 32);
define('SQL_DROP', 64);
define('SQL_CREATE', 128);
define('SQL_SHOW', 256);
define('SQL_RENAME', 512);
define('SQL_SET', 1024);

class Base
{
    private $_dsn;

    private $_db;

    /**
     * @var Logger
     */
    public $logger;

    public function __construct($dsn)
    {
        $this->set_dsn($dsn);
    }

    public function set_dsn($dsn)
    {
        $this->_dsn = $dsn;
    }

    public function get_dsn()
    {
        return $this->_dsn;
    }

    public function set_db($db)
    {
        $this->_db = $db;
    }

    public function get_db()
    {
        return $this->_db;
    }

    public function set_logger($logger)
    {
        if (!($logger instanceof Logger)) {
            throw new OuzoMigrationsException('Logger parameter must be instance of Logger', OuzoMigrationsException::INVALID_ARGUMENT);
        }
        $this->logger = $logger;
    }

    /**
     * @return Logger
     */
    public function get_logger()
    {
        return $this->logger;
    }

    public function has_table($tbl)
    {
        return $this->table_exists($tbl);
    }

    public function column_definition($column_name, $type, $options = null)
    {
        $col = new ColumnDefinition($this, $column_name, $type, $options);
        return $col->__toString();
    }

    protected function determine_query_type($query)
    {
        $query = strtolower(trim($query));
        $match = array();
        preg_match('/^(\w)*/i', $query, $match);
        $type = $match[0];

        switch ($type) {
            case 'select':
                return SQL_SELECT;
            case 'update':
                return SQL_UPDATE;
            case 'delete':
                return SQL_DELETE;
            case 'insert':
                return SQL_INSERT;
            case 'alter':
                return SQL_ALTER;
            case 'drop':
                return SQL_DROP;
            case 'create':
                return SQL_CREATE;
            case 'show':
                return SQL_SHOW;
            case 'rename':
                return SQL_RENAME;
            case 'set':
                return SQL_SET;
            case 'pragma':
                return SQL_SHOW;
            default:
                return SQL_UNKNOWN_QUERY_TYPE;
        }
    }

    public function create_schema_version_table()
    {
        if (!$this->has_table(RUCKUSING_TS_SCHEMA_TBL_NAME)) {
            $t = $this->create_table(RUCKUSING_TS_SCHEMA_TBL_NAME, array('id' => false));
            $t->column('version', 'string');
            $t->finish();
            $this->add_index(RUCKUSING_TS_SCHEMA_TBL_NAME, 'version', array('unique' => true));
        }
    }

    public function execute($query)
    {
        return $this->query($query);
    }

    public function select_all($query)
    {
        return $this->query($query);
    }

    public function execute_ddl($ddl)
    {
        $this->query($ddl);
        return true;
    }

    public function create_table($table_name, $options = array())
    {
        return new TableDefinition($this, $table_name, $options);
    }
}