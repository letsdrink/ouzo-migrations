<?php
namespace OuzoMigrations\Adapter;

use Ouzo\Utilities\Arrays;
use OuzoMigrations\OuzoMigrationsException;
use PDO;
use Task\Db\MigrateTask;

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

abstract class AdapterBase implements AdapterInterface
{
    /**
     * @var PDO
     */
    protected $_dbHandle;

    private $_databaseName;

    public function __construct($params)
    {
        $this->_dbHandle = $this->_createPdo($params);
        $this->_databaseName = $params['database'];
    }

    private function _createPdo($params)
    {
        $dsn = Arrays::getValue($params, 'dsn');
        if ($dsn) {
            return new PDO($dsn);
        }
        $dsn = $this->_buildDsn($params);
        return new PDO($dsn, $params['user'], $params['password']);
    }

    private function _buildDsn($params)
    {
        $charset = Arrays::getValue($params, 'charset');
        $dsn = $params['type'] . ':host=' . $params['host'] . ';port=' . $params['port'] . ';dbname=' . $params['database'] . ';user=' . $params['user'] . ';password=' . $params['password'];
        return $dsn . ($charset ? ';charset=' . $charset : '');
    }

    public function hasTable($tableName)
    {
        return $this->tableExists($tableName);
    }

    public function createSchemaVersionTable()
    {
        if (!$this->hasTable(MigrateTask::OUZO_MIGRATIONS_SCHEMA_TABLE_NAME)) {
            $table = $this->createTable(MigrateTask::OUZO_MIGRATIONS_SCHEMA_TABLE_NAME, array('id' => false));
            $table->column('version', 'string');
            $table->finish();
            $this->add_index(MigrateTask::OUZO_MIGRATIONS_SCHEMA_TABLE_NAME, 'version', array('unique' => true));
        }
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

    public function getDatabaseName()
    {
        return $this->_databaseName;
    }

    public function startTransaction()
    {
        if (!$this->_dbHandle->inTransaction()) {
            $this->_dbHandle->beginTransaction();
        }
    }

    public function commitTransaction()
    {
        if ($this->_dbHandle->inTransaction()) {
            $this->_dbHandle->commit();
        }
    }

    public function rollbackTransaction()
    {
        if ($this->_dbHandle->inTransaction()) {
            $this->_dbHandle->rollBack();
        }
    }

    abstract public function createTable($tableName, array $options);

    abstract public function tableExists($tableName);

    abstract public function supportsMigrations();

    abstract public function nativeDatabaseTypes();

    public function quote_table($table)
    {

    }

    public function database_exists($db)
    {

    }

    public function create_database($db)
    {

    }

    public function drop_database($db)
    {

    }

    public function schema($output_file)
    {

    }

    public function select_one($query)
    {

    }

    public function drop_table($tbl)
    {

    }

    public function quote_string($str)
    {

    }

    public function identifier($str)
    {

    }

    public function quote($value)
    {

    }

    public function rename_table($name, $new_name)
    {

    }

    public function add_column($table_name, $column_name, $type, $options = array())
    {

    }

    public function remove_column($table_name, $column_name)
    {

    }

    public function rename_column($table_name, $column_name, $new_column_name)
    {

    }

    public function change_column($table_name, $column_name, $type, $options = array())
    {

    }

    public function column_info($table, $column)
    {

    }

    public function add_index($table_name, $column_name, $options = array())
    {

    }

    public function remove_index($table_name, $column_name)
    {

    }

    public function has_index($table_name, $column_name, $options = array())
    {

    }

    public function indexes($table_name)
    {

    }

    public function type_to_sql($type, $options = array())
    {

    }

    public function primary_keys($table_name)
    {

    }

    public function add_column_options($type, $options, $performing_change = false)
    {

    }

    public function set_current_version($version)
    {

    }

    public function remove_version($version)
    {

    }

    public function query($query)
    {

    }
}

class AdapterBaseException extends OuzoMigrationsException
{
}