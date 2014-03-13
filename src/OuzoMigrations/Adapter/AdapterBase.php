<?php
namespace OuzoMigrations\Adapter;

use Ouzo\Utilities\Arrays;
use OuzoMigrations\OuzoMigrationsException;
use PDO;
use Task\Db\MigrateTask;

abstract class AdapterBase implements AdapterInterface
{
    const SQL_UNKNOWN_QUERY_TYPE = 1;
    const SQL_SELECT = 2;
    const SQL_INSERT = 4;
    const SQL_UPDATE = 8;
    const SQL_DELETE = 16;
    const SQL_ALTER = 32;
    const SQL_DROP = 64;
    const SQL_CREATE = 128;
    const SQL_SHOW = 256;
    const SQL_RENAME = 512;
    const SQL_SET = 1024;

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

    /**
     * @SuppressWarnings(PHPMD)
     */
    protected function determineQueryType($query)
    {
        $query = strtolower(trim($query));
        $match = array();
        preg_match('/^(\w)*/i', $query, $match);
        $type = $match[0];

        switch ($type) {
            case 'select':
                return self::SQL_SELECT;
            case 'update':
                return self::SQL_UPDATE;
            case 'delete':
                return self::SQL_DELETE;
            case 'insert':
                return self::SQL_INSERT;
            case 'alter':
                return self::SQL_ALTER;
            case 'drop':
                return self::SQL_DROP;
            case 'create':
                return self::SQL_CREATE;
            case 'show':
            case 'pragma':
                return self::SQL_SHOW;
            case 'rename':
                return self::SQL_RENAME;
            case 'set':
                return self::SQL_SET;
            default:
                return self::SQL_UNKNOWN_QUERY_TYPE;
        }
    }

    public function execute($query)
    {
        return $this->query($query);
    }

    public function selectAll($query)
    {
        return $this->query($query);
    }

    public function executeDdl($ddl)
    {
        return $this->query($ddl);
    }

    public function getDatabaseName()
    {
        return $this->_databaseName;
    }

    public function beginTransaction()
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

    abstract public function quoteTable($table);

    abstract public function quoteString($string);

    abstract public function quote($value);

    //FIXME: check is required?
    //abstract public function quoteColumnName($column);

    abstract public function databaseExists($db);

    abstract public function createDatabase($db);

    abstract public function dropDatabase($db);

    abstract public function identifier($string);

    abstract public function typeToSql($type, $options = array());

    abstract public function addColumnOptions($type, $options);

    abstract public function query($query);

    abstract public function selectOne($query);

    public function schema($output_file)
    {

    }

    public function drop_table($tbl)
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

    public function primary_keys($table_name)
    {

    }

    public function set_current_version($version)
    {

    }

    public function remove_version($version)
    {

    }
}

class AdapterBaseException extends OuzoMigrationsException
{
}