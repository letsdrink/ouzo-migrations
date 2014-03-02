<?php
namespace Ruckusing\Util;

use Ruckusing\Adapter\Base;
use Ruckusing\RuckusingException;

class Migrator
{
    /**
     * adapter
     *
     * @var Base
     */
    private $_adapter = null;

    /**
     * migrations
     *
     * @var array
     */
    private $_migrations = array();

    public function __construct($adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * @param $adapter
     * @throws RuckusingException
     * @return Migrator
     */
    public function setAdapter($adapter)
    {
        if (!($adapter instanceof Base)) {
            throw new RuckusingException('Adapter must be implement Base!', RuckusingException::INVALID_ADAPTER);
        }
        $this->_adapter = $adapter;

        return $this;
    }

    /**
     * Return the max version number from the DB, or "0" in the case of no versions available.
     * We must use strings because our date/timestamp when treated as an integer would cause overflow.
     *
     * @return string
     */
    public function get_max_version()
    {
        // We only want one row but we cannot assume that we are using MySQL and use a LIMIT statement
        // as it is not part of the SQL standard. Thus we have to select all rows and use PHP to return
        // the record we need
        $versions_nested = $this->_adapter->select_all(sprintf("SELECT version FROM %s", RUCKUSING_TS_SCHEMA_TBL_NAME));
        $versions = array();
        foreach ($versions_nested as $v) {
            $versions[] = $v['version'];
        }
        $num_versions = count($versions);
        if ($num_versions) {
            sort($versions); //sorts lowest-to-highest (ascending)
            return (string)$versions[$num_versions - 1];
        } else {
            return null;
        }
    }

    /**
     * This methods calculates the actual set of migrations that should be performed, taking into account
     * the current version, the target version and the direction (up/down). When going up this method will
     * skip migrations that have not been executed, when going down this method will only include migrations
     * that have been executed.
     *
     * @param array $directories the migration dirs
     * @param string $direction up/down
     * @param string $destination the version to migrate to
     * @param boolean $use_cache the current logger
     *
     * @throws \Ruckusing\RuckusingException
     * @return array
     */
    public function get_runnable_migrations($directories, $direction, $destination = null, $use_cache = true)
    {
        // cache migration lookups and early return if we've seen this requested set
        if ($use_cache == true) {
            $key = $direction . '-' . $destination;
            if (array_key_exists($key, $this->_migrations)) {
                return ($this->_migrations[$key]);
            }
        }

        $runnable = array();
        $migrations = array();
        $migrations = $this->get_migration_files($directories, $direction);
        $current = $this->find_version($migrations, $this->get_max_version());
        $target = $this->find_version($migrations, $destination);
        if (is_null($target) && !is_null($destination) && $destination > 0) {
            throw new RuckusingException(
                "Could not find target version {$destination} in set of migrations.",
                RuckusingException::INVALID_TARGET_MIGRATION
            );
        }
        $start = $direction == 'up' ? 0 : array_search($current, $migrations);
        $start = $start !== false ? $start : 0;
        $finish = array_search($target, $migrations);
        $finish = $finish !== false ? $finish : (count($migrations) - 1);
        $item_length = ($finish - $start) + 1;

        $runnable = array_slice($migrations, $start, $item_length);

        //dont include first item if going down but not if going all the way to the bottom
        if ($direction == 'down' && count($runnable) > 0 && $target != null) {
            array_pop($runnable);
        }

        $executed = $this->get_executed_migrations();
        $to_execute = array();

        foreach ($runnable as $migration) {
            //Skip ones that we have already executed
            if ($direction == 'up' && in_array($migration['version'], $executed)) {
                continue;
            }
            //Skip ones that we never executed
            if ($direction == 'down' && !in_array($migration['version'], $executed)) {
                continue;
            }
            $to_execute[] = $migration;
        }
        if ($use_cache == true) {
            $this->_migrations[$key] = $to_execute;
        }

        return ($to_execute);
    }

    public static function generate_timestamp()
    {
        return gmdate('YmdHis', time());
    }

    public function resolve_current_version($version, $direction)
    {
        if ($direction === 'up') {
            $this->_adapter->set_current_version($version);
        }
        if ($direction === 'down') {
            $this->_adapter->remove_version($version);
        }

        return $version;
    }

    public function get_executed_migrations()
    {
        return $this->executed_migrations();
    }

    /**
     * Return a set of migration files, according to the given direction.
     * If nested, then return a complex array with the migration parts broken up into parts
     * which make analysis much easier.
     *
     * @param array $directories the migration dirs
     * @param string $direction the direction  up/down
     *
     * @throws \Ruckusing\RuckusingException
     * @return array
     */
    public static function get_migration_files($directories, $direction)
    {
        $valid_files = array();
        foreach ($directories as $name => $path) {
            if (!is_dir($path)) {
                if (mkdir($path, 0755, true) === FALSE) {
                    throw new RuckusingException("\n\tUnable to create migrations directory at %s, check permissions?", $path, RuckusingException::INVALID_MIGRATION_DIR);
                }
            }
            $files = scandir($path);
            $file_cnt = count($files);
            if ($file_cnt > 0) {
                for ($i = 0; $i < $file_cnt; $i++) {
                    if (preg_match('/^(\d+)_(.*)\.php$/', $files[$i], $matches)) {
                        if (count($matches) == 3) {
                            $valid_files[] = array(
                                'name' => $files[$i],
                                'module' => $name
                            );
                        }
                    }
                }
            }
        }

        usort($valid_files, array("Ruckusing_Util_Migrator", "migration_compare")); //sorts in place

        if ($direction == 'down') {
            $valid_files = array_reverse($valid_files);
        }

        //user wants a nested structure
        $files = array();
        $cnt = count($valid_files);
        for ($i = 0; $i < $cnt; $i++) {
            $migration = $valid_files[$i];
            if (preg_match('/^(\d+)_(.*)\.php$/', $migration['name'], $matches)) {
                $files[] = array(
                    'version' => $matches[1],
                    'class' => $matches[2],
                    'file' => $matches[0],
                    'module' => $migration['module']
                );
            }
        }

        return $files;
    }

    /**
     * Find the specified structure (representing a migration) that matches the given version
     *
     * @param array $migrations the list of migrations
     * @param string $version the version being searched
     * @param boolean $only_index whether to only return the index of the version
     *
     * @return null | integer | array
     */
    public function find_version($migrations, $version, $only_index = false)
    {
        $len = count($migrations);
        for ($i = 0; $i < $len; $i++) {
            if ($migrations[$i]['version'] == $version) {
                return $only_index ? $i : $migrations[$i];
            }
        }

        return null;
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    private static function migration_compare($a, $b)
    {
        return strcmp($a["name"], $b["name"]);
    }

    /**
     * Query the database and return a list of migration versions that *have* been executed
     *
     * @return array
     */
    private function executed_migrations()
    {
        $query_sql = sprintf('SELECT version FROM %s', RUCKUSING_TS_SCHEMA_TBL_NAME);
        $versions = $this->_adapter->select_all($query_sql);
        $executed = array();
        foreach ($versions as $v) {
            $executed[] = $v['version'];
        }
        sort($executed);

        return $executed;
    }
}