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

        return $migrations;

//        $start = $direction == 'up' ? 0 : array_search($currentVersion, $migrations);
//        $start = $start !== false ? $start : 0;
//
//        $finish = array_search($targetVersion, $migrations);
//        $finish = $finish !== false ? $finish : (count($migrations) - 1);
//
//        $item_length = ($finish - $start) + 1;
//
//        $runnable = array_slice($migrations, $start, $item_length);
//
//        //dont include first item if going down but not if going all the way to the bottom
//        if ($direction == 'down' && count($runnable) > 0 && $targetVersion != null) {
//            array_pop($runnable);
//        }
//
//        $executed = $this->get_executed_migrations();
//        $to_execute = array();
//
//        foreach ($runnable as $migration) {
//            //Skip ones that we have already executed
//            if ($direction == 'up' && in_array($migration['version'], $executed)) {
//                continue;
//            }
//            //Skip ones that we never executed
//            if ($direction == 'down' && !in_array($migration['version'], $executed)) {
//                continue;
//            }
//            $to_execute[] = $migration;
//        }

//        return ($to_execute);
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

    public static function generate_timestamp()
    {
        return gmdate('YmdHis', time());
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

    public function get_executed_migrations()
    {
        return $this->executed_migrations();
    }

    private function executed_migrations()
    {
        $query_sql = sprintf('SELECT version FROM %s', RUCKUSING_TS_SCHEMA_TBL_NAME);
        $versions = $this->_adapter->selectAll($query_sql);
        $executed = array();
        foreach ($versions as $v) {
            $executed[] = $v['version'];
        }
        sort($executed);

        return $executed;
    }
}