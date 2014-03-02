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
}