<?php
namespace OuzoMigrations\Util;

use DirectoryIterator;
use Ouzo\Config;
use Ouzo\Utilities\Arrays;
use OuzoMigrations\Adapter\AdapterBase;
use OuzoMigrations\OuzoMigrationsException;
use Task\Db\MigrateTask;

class Migrator
{
    /**
     * @var AdapterBase
     */
    private $_adapter = null;

    public function __construct(AdapterBase $adapter)
    {
        $this->_adapter = $adapter;
    }

    public function getMaxVersion()
    {
        $versions = $this->_adapter->selectAll("SELECT version FROM " . MigrateTask::OUZO_MIGRATIONS_SCHEMA_TABLE_NAME);
        usort($versions, function ($a, $b) {
            return $a['version'] > $b['version'] ? -1 : 1;
        });
        $first = Arrays::firstOrNull($versions) ? : array();
        return Arrays::getValue($first, 'version');
    }

    public static function getMigrationDir($module = 'default')
    {
        return Config::getValue('migrations_dir', $module);
    }

    public function getRunnableMigrations($migrationDir, $direction, $destination = null)
    {
        $migrations = self::getMigrationFiles($migrationDir, $direction);

        $currentVersion = $this->findVersion($migrations, $this->getMaxVersion());
        $targetVersion = $this->findVersion($migrations, $destination);

        if (!$targetVersion && $destination && $destination > 0) {
            throw new OuzoMigrationsException("Could not find target version {$destination} in set of migrations.", OuzoMigrationsException::INVALID_TARGET_MIGRATION);
        }

        $executedMigrations = $this->getExecutedMigrations();
        $migrationsToExecute = Arrays::filter($migrations, function (MigrationFile $file) use ($executedMigrations) {
            return !in_array($file->getVersion(), $executedMigrations);
        });

        return $migrationsToExecute;
    }

    public static function getMigrationFiles($path, $direction)
    {
        if (!file_exists($path)) {
            throw new OuzoMigrationsException("Could not find target migrations dir {$path}.", OuzoMigrationsException::INVALID_MIGRATION_DIR);
        }

        $files = array();

        $migrationsDir = new DirectoryIterator($path);
        foreach ($migrationsDir as $migrationFile) {
            if (preg_match('/^(\d+)_(.*)\.php$/', $migrationFile->getFilename())) {
                $files[] = new MigrationFile($migrationFile);
            }
        }

        usort($files, array("\\OuzoMigrations\\Util\\Migrator", "_migrationCompare"));

        if ($direction == MigrateTask::MIGRATION_DOWN) {
            $files = array_reverse($files);
        }

        return $files;
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    private static function _migrationCompare(MigrationFile $a, MigrationFile $b)
    {
        return strcmp($a->getFilename(), $b->getFilename());
    }

    /**
     * @param MigrationFile[] $migrationFiles
     * @param $version
     * @return null
     */
    public function findVersion($migrationFiles, $version)
    {
        foreach ($migrationFiles as $migrationFile) {
            if ($migrationFile->getVersion() == $version) {
                return $migrationFiles;
            }
        }
        return null;
    }

    public function getExecutedMigrations()
    {
        $query = 'SELECT version FROM ' . MigrateTask::OUZO_MIGRATIONS_SCHEMA_TABLE_NAME . ' ORDER BY version';
        $rows = $this->_adapter->selectAll($query);
        $executed = array();
        foreach ($rows as $row) {
            $executed[] = $row['version'];
        }
        return $executed;
    }

    public function resolveCurrentVersion($version, $direction)
    {
        if ($direction === MigrateTask::MIGRATION_UP) {
            $this->_adapter->setCurrentVersion($version);
        }
        if ($direction === MigrateTask::MIGRATION_DOWN) {
            $this->_adapter->remove_version($version);
        }
        return $version;
    }
}