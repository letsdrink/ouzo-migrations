<?php
namespace Ruckusing\Util;

class Logger
{
    /**
     * Instance of logger
     *
     * @var Logger
     */
    private static $_instance;

    /**
     * file
     *
     * @var string
     */
    private $_file = '';

    /**
     * File descriptor
     *
     * @var resource
     */
    private $_fp;

    /**
     * Creates an instance of Logger
     *
     * @param string $file the path to log to
     *
     * @return Logger
     */
    public function __construct($file)
    {
        $this->_file = $file;
        $this->_fp = fopen($this->_file, "a+");
    }

    /**
     * Close the file descriptor
     *
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Singleton for the instance
     *
     * @param string $logfile the path to log to
     *
     * @return object
     */
    public static function instance($logfile)
    {
        if (self::$_instance !== NULL) {
            return $instance;
        }
        $instance = new Logger($logfile);

        return $instance;
    }

    /**
     * Log a message
     *
     * @param string $msg message to log
     */
    public function log($msg)
    {
        if ($this->_fp) {
            $ts = date('M d H:i:s', time());
            $line = sprintf("%s [info] %s\n", $ts, $msg);
            fwrite($this->_fp, $line);
        } else {
            throw new Ruckusing_Exception(
                sprintf("Error: logfile '%s' not open for writing!", $this->_file),
                Ruckusing_Exception::INVALID_LOG
            );
        }

    }

    /**
     * Close the log file handler
     */
    public function close()
    {
        if ($this->_fp) {
            $closed = fclose($this->_fp);
            if ($closed) {
                $this->_fp = null;
                self::$_instance = null;
            } else {
                throw new Ruckusing_Exception(
                    'Error closing the log file',
                    Ruckusing_Exception::INVALID_LOG
                );
            }
        }
    }

}
