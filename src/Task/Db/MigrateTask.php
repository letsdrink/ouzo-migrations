<?php
namespace Task\Db;

use Exception;
use Ouzo\Utilities\Files;
use OuzoMigrations\Adapter\AdapterBase;
use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Task\TaskInterface;
use OuzoMigrations\Util\MigrationFile;
use OuzoMigrations\Util\Migrator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

                $className = $migrationFile->getClassName();
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
                $this->_writeln(sprintf("========= %s ========= (%.2f)\n", $migrationFile->getClassName(), $deltaTimer));
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
        $targetVersion = $this->_input->getArgument('version');
        $currentVersion = $this->_migrator->getMaxVersion();

        if (!$targetVersion) {
            return self::MIGRATION_UP;
        } elseif ($currentVersion > $targetVersion) {
            return self::MIGRATION_DOWN;
        } else {
            return self::MIGRATION_UP;
        }
    }
}