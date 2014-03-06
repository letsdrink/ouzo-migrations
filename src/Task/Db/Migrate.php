<?php
namespace Task\Db;

use Exception;
use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Util\Migrator;

define('STYLE_REGULAR', 1);
define('STYLE_OFFSET', 2);

class Task_Db_Migrate extends \OuzoMigrations\Task\Base implements \OuzoMigrations\Task\TaskInterface
{
    /**
     * @var Migrator
     */
    private $_migrator_util = null;

    /**
     * @var \OuzoMigrations\Adapter\Base
     */
    private $_adapter = null;

    private $_migratorDirs = null;

    private $_task_args = array();

    private $_debug = false;

    private $_return = '';

    public function __construct($adapter)
    {
        parent::__construct($adapter);
        $this->_adapter = $adapter;
        $this->_migrator_util = new Migrator($this->_adapter);
    }

    public function execute($args)
    {
        if (!$this->_adapter->supports_migrations()) {
            throw new OuzoMigrationsException("This database does not support migrations.", OuzoMigrationsException::MIGRATION_NOT_SUPPORTED);
        }
        $this->_task_args = $args;
        $this->_return .= "Started: " . date('Y-m-d g:ia T') . "\n\n";
        $this->_return .= "[db:migrate]: \n";
        try {
            // Check that the schema_version table exists, and if not, automatically create it
            $this->verify_environment();

            $target_version = null;
            $style = STYLE_REGULAR;

            //did the user specify an explicit version?
            if (array_key_exists('version', $this->_task_args)) {
                $target_version = trim($this->_task_args['version']);
            }

            // did the user specify a relative offset, e.g. "-2" or "+3" ?
            if ($target_version !== null) {
                if (preg_match('/^([\-\+])(\d+)$/', $target_version, $matches)) {
                    if (count($matches) == 3) {
                        $direction = $matches[1] == '-' ? 'down' : 'up';
                        $steps = intval($matches[2]);
                        $style = STYLE_OFFSET;
                    }
                }
            }
            //determine our direction and target version
            $current_version = $this->_migrator_util->get_max_version();
            if ($style == STYLE_REGULAR) {
                if (is_null($target_version)) {
                    $this->prepare_to_migrate($target_version, 'up');
                } elseif ($current_version > $target_version) {
                    $this->prepare_to_migrate($target_version, 'down');
                } else {
                    $this->prepare_to_migrate($target_version, 'up');
                }
            }

            if ($style == STYLE_OFFSET) {
                $this->migrate_from_offset($steps, $current_version, $direction);
            }

            // Completed - display accumulated output
            if (!empty($output)) {
                $this->_return .= "\n\n";
            }
        } catch (OuzoMigrationsException $ex) {
            if ($ex->getCode() == OuzoMigrationsException::MISSING_SCHEMA_INFO_TABLE) {
                $this->_return .= "\tSchema info table does not exist. I tried creating it but failed. Check permissions.";
            } else {
                throw $ex;
            }
        }
        $this->_return .= "\n\nFinished: " . date('Y-m-d g:ia T') . "\n\n";

        return $this->_return;
    }

    private function migrate_from_offset($steps, $current_version, $direction)
    {
        $migrations = $this->_migrator_util->get_migration_files($this->_migratorDirs, $direction);

        $current_index = $this->_migrator_util->find_version($migrations, $current_version, true);
        $current_index = $current_index !== null ? $current_index : -1;

        if ($this->_debug == true) {
            $this->_return .= print_r($migrations, true);
            $this->_return .= "\ncurrent_index: " . $current_index . "\n";
            $this->_return .= "\ncurrent_version: " . $current_version . "\n";
            $this->_return .= "\nsteps: " . $steps . " $direction\n";
        }

        // If we are not at the bottom then adjust our index (to satisfy array_slice)
        if ($current_index == -1 && $direction === 'down') {
            $available = array();
        } else {
            if ($direction === 'up') {
                $current_index += 1;
            } else {
                $current_index += $steps;
            }
            // check to see if we have enough migrations to run - the user
            // might have asked to run more than we have available
            $available = array_slice($migrations, $current_index, $steps);
        }

        $target = end($available);
        if ($this->_debug == true) {
            $this->_return .= "\n------------- TARGET ------------------\n";
            $this->_return .= print_r($target, true);
        }
        $this->prepare_to_migrate(isset($target['version']) ? $target['version'] : null, $direction);
    }

    private function prepare_to_migrate($destination, $direction)
    {
        try {
            $this->_return .= "\tMigrating " . strtoupper($direction);
            if (!is_null($destination)) {
                $this->_return .= " to: {$destination}\n";
            } else {
                $this->_return .= ":\n";
            }
            $migrations = $this->_migrator_util->get_runnable_migrations(
                $this->_migratorDirs,
                $direction,
                $destination
            );
            if (count($migrations) == 0) {
                $this->_return .= "\nNo relevant migrations to run. Exiting...\n";

                return;
            }
            $result = $this->run_migrations($migrations, $direction, $destination);
        } catch (Exception $ex) {
            throw $ex;
        }

    }

    private function run_migrations($migrations, $target_method, $destination)
    {
        $last_version = -1;
        foreach ($migrations as $file) {
            $full_path = $this->_migratorDirs[$file['module']] . DIRECTORY_SEPARATOR . $file['file'];
            if (is_file($full_path) && is_readable($full_path)) {
                require_once $full_path;
                $klass = Ruckusing_Util_Naming::class_from_migration_file($file['file']);
                $obj = new $klass($this->_adapter);
                $start = $this->start_timer();
                try {
                    //start transaction
                    $this->_adapter->start_transaction();
                    $result = $obj->$target_method();
                    //successfully ran migration, update our version and commit
                    $this->_migrator_util->resolve_current_version($file['version'], $target_method);
                    $this->_adapter->commit_transaction();
                } catch (OuzoMigrationsException $e) {
                    $this->_adapter->rollback_transaction();
                    //wrap the caught exception in our own
                    throw new OuzoMigrationsException(sprintf("%s - %s", $file['class'], $e->getMessage()), OuzoMigrationsException::MIGRATION_FAILED);
                }
                $end = $this->end_timer();
                $diff = $this->diff_timer($start, $end);
                $this->_return .= sprintf("========= %s ======== (%.2f)\n", $file['class'], $diff);
                $last_version = $file['version'];
                $exec = true;
            }
        }

        //update the schema info
        $result = array('last_version' => $last_version);

        return $result;
    }

    private function start_timer()
    {
        return microtime(true);
    }

    private function end_timer()
    {
        return microtime(true);
    }

    private function diff_timer($s, $e)
    {
        return $e - $s;
    }

    private function verify_environment()
    {
        if (!$this->_adapter->table_exists(RUCKUSING_TS_SCHEMA_TBL_NAME)) {
            $this->_return .= "\n\tSchema version table does not exist. Auto-creating.";
            $this->auto_create_schema_info_table();
        }

        $this->_migratorDirs = $this->get_framework()->migrations_directories();

        // create the migrations directory if it doesnt exist
        foreach ($this->_migratorDirs as $name => $path) {
            if (!is_dir($path)) {
                $this->_return .= sprintf("\n\tMigrations directory (%s) doesn't exist, attempting to create.", $path);
                if (mkdir($path, 0755, true) === FALSE) {
                    $this->_return .= sprintf("\n\tUnable to create migrations directory at %s, check permissions?", $path);
                } else {
                    $this->_return .= sprintf("\n\tCreated OK");
                }
            }
            //check to make sure our destination directory is writable
            if (!is_writable($path)) {
                throw new OuzoMigrationsException("ERROR: Migrations directory '" . $path . "' is not writable by the current user. Check permissions and try again.\n", OuzoMigrationsException::INVALID_MIGRATION_DIR);
            }
        }
    }

    private function auto_create_schema_info_table()
    {
        try {
            $this->_return .= sprintf("\n\tCreating schema version table: %s", RUCKUSING_TS_SCHEMA_TBL_NAME . "\n\n");
            $this->_adapter->create_schema_version_table();
            return true;
        } catch (Exception $e) {
            throw new OuzoMigrationsException("\nError auto-creating 'schema_info' table: " . $e->getMessage() . "\n\n", OuzoMigrationsException::MIGRATION_FAILED);
        }
    }

    public function help()
    {
        $output = <<<USAGE

\tTask: db:migrate [VERSION]

\tThe primary purpose of the framework is to run migrations, and the
\texecution of migrations is all handled by just a regular ol' task.

\tVERSION can be specified to go up (or down) to a specific
\tversion, based on the current version. If not specified,
\tall migrations greater than the current database version
\twill be executed.

\tExample A: The database is fresh and empty, assuming there
\tare 5 actual migrations, but only the first two should be run.

\t\tphp {$_SERVER['argv'][0]} db:migrate VERSION=20101006114707

\tExample B: The current version of the DB is 20101006114707
\tand we want to go down to 20100921114643

\t\tphp {$_SERVER['argv'][0]} db:migrate VERSION=20100921114643

\tExample C: You can also use relative number of revisions
\t(positive migrate up, negative migrate down).

\t\tphp {$_SERVER['argv'][0]} db:migrate VERSION=-2

USAGE;

        return $output;
    }
}