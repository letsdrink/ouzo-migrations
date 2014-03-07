<?php
namespace Task\Db;

use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Task\Base;
use OuzoMigrations\Task\TaskInterface;

class Schema extends Base implements TaskInterface
{
    /**
     * @var \OuzoMigrations\Adapter\AdapterBase
     */
    private $_adapter = null;

    private $_return = '';

    public function __construct($adapter)
    {
        parent::__construct($adapter);
        $this->_adapter = $adapter;
    }

    public function execute($args)
    {
        $this->_return .= "Started: " . date('Y-m-d g:ia T') . "\n\n";
        $this->_return .= "[db:schema]: \n";

        //write to disk
        $schema_file = $this->db_dir() . '/schema.txt';
        $schema = $this->_adapter->schema($schema_file);
        $this->_return .= "\tSchema written to: $schema_file\n\n";
        $this->_return .= "\n\nFinished: " . date('Y-m-d g:ia T') . "\n\n";

        return $this->_return;
    }

    private function db_dir()
    {
        // create the db directory if it doesnt exist
        $db_directory = $this->get_framework()->db_directory();
        if (!is_dir($db_directory)) {
            $this->_return .= sprintf("\n\tDB Schema directory (%s doesn't exist, attempting to create.\n", $db_directory);
            if (mkdir($db_directory, 0755, true) === FALSE) {
                $this->_return .= sprintf("\n\tUnable to create migrations directory at %s, check permissions?\n", $db_directory);
            } else {
                $this->_return .= sprintf("\n\tCreated OK\n\n");
            }
        }

        //check to make sure our destination directory is writable
        if (!is_writable($db_directory)) {
            throw new OuzoMigrationsException("ERROR: DB Schema directory '" . $db_directory . "' is not writable by the current user. Check permissions and try again.\n", OuzoMigrationsException::INVALID_DB_DIR);
        }

        return $db_directory;
    }

    public function help()
    {
        $output = <<<USAGE

\tTask: db:schema

\tIt can be beneficial to get a dump of the DB in raw SQL format which represents
\tthe current version.

\tNote: This dump only contains the actual schema (e.g. the DML needed to
\treconstruct the DB), but not any actual data.

\tIn MySQL terms, this task would not be the same as running the mysqldump command
\t(which by defaults does include any data in the tables).

USAGE;

        return $output;
    }
}