<?php
namespace OuzoMigrations\Util;

use OuzoMigrations\OuzoMigrationsException;

class Logger
{
    /**
     * @var Logger
     */
    private static $_instance;

    private $_file = '';

    private $_fp;

    public function __construct($file)
    {
        $this->_file = $file;
        $this->_fp = fopen($this->_file, "a+");
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param $logfile
     * @return Logger
     */
    public static function instance($logfile)
    {
        if (self::$_instance !== NULL) {
            return self::$_instance;
        }
        return new Logger($logfile);
    }

    public function log($msg)
    {
        if ($this->_fp) {
            $ts = date('M d H:i:s', time());
            $line = sprintf("%s [info] %s\n", $ts, $msg);
            fwrite($this->_fp, $line);
        } else {
            throw new OuzoMigrationsException(sprintf("Error: logfile '%s' not open for writing!", $this->_file), OuzoMigrationsException::INVALID_LOG);
        }
    }

    public function close()
    {
        if ($this->_fp) {
            $closed = fclose($this->_fp);
            if ($closed) {
                $this->_fp = null;
                self::$_instance = null;
            } else {
                throw new OuzoMigrationsException('Error closing the log file', OuzoMigrationsException::INVALID_LOG);
            }
        }
    }
}