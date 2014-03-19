<?php
namespace Task\Db;

use Exception;
use Ouzo\Utilities\Files;
use OuzoMigrations\Adapter\AdapterBase;
use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Task\TaskInterface;
use OuzoMigrations\Util\MigrationFile;
use OuzoMigrations\Util\Migrator;
use OuzoMigrations\Util\Naming;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

define('STYLE_REGULAR', 1);
define('STYLE_OFFSET', 2);

class MigrateTask implements TaskInterface
{
    const OUZO_MIGRATIONS_SCHEMA_TABLE_NAME = 'schema_migrations';
    const MIGRATION_UP = 'UP';
    const MIGRATION_DOWN = 'DOWN';

    /**
     * @var InputInterface
     */
    private $_input;
    /**
     * @var OutputInterface
     */
    private $_output;
    /**
     * @var AdapterBase
     */
    private $_adapter;
    /**
     * @var Migrator
     */
    private $_migrator;
    private $_migrationDir = null;

    function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->_input = $input;
        $this->_output = $output;
    }

    public function setAdapterAndMigrator(AdapterBase $adapter)
    {
        $this->_adapter = $adapter;
        $this->_migrator = new Migrator($adapter);
    }

    private function _writeln($message)
    {
        $this->_output->writeln($message);
    }

    private function _write($message)
    {
        $this->_output->write($message);
    }

    public function execute()
    {
        $this->_writeln("<info>[db:migrate]</info>");
        $this->_writeln("\tUsing database: <comment>" . $this->_adapter->getDatabaseName() . "</comment>\n");

        $this->_checkMigrationTableAndCreate();
        $this->_setMigrationDir();
        $this->_prepareToMigrate();
    }

    private function _checkMigrationTableAndCreate()
    {
        $schemaTable = $this->_adapter->hasTable(self::OUZO_MIGRATIONS_SCHEMA_TABLE_NAME);
        if (!$schemaTable) {
            $this->_writeln("\t<info>Schema version table does not exist.</info> Auto-creating.");
            $this->_createSchemaTable();
        }
    }

    private function _createSchemaTable()
    {
        try {
            $this->_writeln("\tCreating schema version table: <info>" . self::OUZO_MIGRATIONS_SCHEMA_TABLE_NAME . "</info>.");
            $this->_adapter->createSchemaVersionTable();
        } catch (Exception $e) {
            throw new OuzoMigrationsException("Error auto-creating 'schema_info' table: " . $e->getMessage(), OuzoMigrationsException::MIGRATION_FAILED);
        }
    }

    private function _setMigrationDir()
    {
        $this->_migrationDir = Migrator::getMigrationDir();
    }

    private function _prepareToMigrate()
    {
        $direction = $this->_direction();
        $destination = null;

        $this->_write("\tMigrating in direction: <info>" . $direction . '</info>');

        if (!$destination) {
            $this->_writeln(":\n");
        } else {
            $this->_writeln(" to: " . $destination . "\n");
        }

        $migrations = $this->_migrator->getRunnableMigrations($this->_migrationDir, $direction, $destination);

        if (!$migrations) {
            $this->_writeln("<info>No relevant migrations to run.</info> Exiting...");
            return;
        }
        $this->runMigrations($migrations, $direction);
    }

    /**
     * @param MigrationFile[] $migrationFiles
     * @param $targetMethod
     * @return array
     * @throws OuzoMigrationsException
     */
    private function runMigrations($migrationFiles, $targetMethod)
    {
        $lastVersion = -1;
        foreach ($migrationFiles as $migrationFile) {
            $fullPath = $migrationFile->getFullPath();

            if (is_file($fullPath) && is_readable($fullPath)) {
                Files::load($fullPath);

                $className = Naming::class_from_migration_file($migrationFile->getFilename());
                $obj = new $className($this->_adapter);

                $startTimer = $this->_startTimer();
                try {
                    $this->_adapter->beginTransaction();
                    $obj->$targetMethod();
                    $this->_migrator->resolveCurrentVersion($migrationFile->getVersion(), $targetMethod);
                    $this->_adapter->commitTransaction();
                } catch (OuzoMigrationsException $e) {
                    $this->_adapter->rollbackTransaction();
                    throw new OuzoMigrationsException(sprintf("%s - %s", $migrationFile->getClassName(), $e->getMessage()), OuzoMigrationsException::MIGRATION_FAILED);
                }
                $endTimer = $this->_endTimer();

                $deltaTimer = $this->_deltaTimer($startTimer, $endTimer);
                $this->_writeln(sprintf("========= %s ======== (%.2f)\n", $migrationFile->getClassName(), $deltaTimer));
                $lastVersion = $migrationFile->getVersion();
            }
        }
        return array('last_version' => $lastVersion);
    }

    private function _startTimer()
    {
        return microtime(true);
    }

    private function _endTimer()
    {
        return microtime(true);
    }

    private function _deltaTimer($start, $end)
    {
        return $end - $start;
    }

    private function _direction()
    {
        $direction = self::MIGRATION_UP;
        if ($this->_input->getArgument('version')) {
        }
        return $direction;
    }

    public function exec($args)
    {
        if (!$this->_adapter->supportsMigrations()) {
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
                    $this->_prepareToMigrate($target_version, 'up');
                } elseif ($current_version > $target_version) {
                    $this->_prepareToMigrate($target_version, 'down');
                } else {
                    $this->_prepareToMigrate($target_version, 'up');
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
        $this->_prepareToMigrate(isset($target['version']) ? $target['version'] : null, $direction);
    }

    private function verify_environment()
    {
        if (!$this->_adapter->tableExists(RUCKUSING_TS_SCHEMA_TBL_NAME)) {
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
            $this->_adapter->createSchemaVersionTable();
            return true;
        } catch (Exception $e) {
            throw new OuzoMigrationsException("\nError auto-creating 'schema_info' table: " . $e->getMessage() . "\n\n", OuzoMigrationsException::MIGRATION_FAILED);
        }
    }
}