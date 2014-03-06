<?php
namespace Task\Db;

use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Util\Migrator;
use OuzoMigrations\Util\Naming;

class Generate extends \OuzoMigrations\Task\Base implements \OuzoMigrations\Task\TaskInterface
{
    /**
     * @var \OuzoMigrations\Adapter\AdapterInterface
     */
    private $_adapter = null;

    public function __construct($adapter)
    {
        parent::__construct($adapter);
        $this->_adapter = $adapter;
    }

    public function execute($args)
    {
        $output = '';
        // Add support for old migration style
        if (!is_array($args) || !array_key_exists('name', $args)) {
            $cargs = $this->parse_args($_SERVER['argv']);
            //input sanity check
            if (!is_array($cargs) || !array_key_exists('name', $cargs)) {
                $output .= $this->help();

                return $output;
            }
            $migration_name = $cargs['name'];
        } // Add NAME= parameter for db:generate
        else {
            $migration_name = $args['name'];
        }
        if (!array_key_exists('module', $args)) {
            $args['module'] = '';
        }

        //clear any filesystem stats cache
        clearstatcache();

        $framework = $this->get_framework();
        $migrations_dir = $framework->migrations_directory($args['module']);

        if (!is_dir($migrations_dir)) {
            $output .= "\n\tMigrations directory (" . $migrations_dir . " doesn't exist, attempting to create.\n";
            if (mkdir($migrations_dir, 0755, true) === FALSE) {
                $output .= "\n\tUnable to create migrations directory at " . $migrations_dir . ", check permissions?\n";
            } else {
                $output .= "\n\tCreated OK\n";
            }
        }

        //generate a complete migration file
        $next_version = Migrator::generate_timestamp();
        $class = Naming::camelcase($migration_name);
        $all_dirs = $framework->migrations_directories();

        if ($re = self::classNameIsDuplicated($class, $all_dirs)) {
            throw new OuzoMigrationsException("This migration name is already used in the \"$re\" directory. Please, choose another name.", OuzoMigrationsException::INVALID_ARGUMENT);
        }

        $file_name = $next_version . '_' . $class . '.php';

        //check to make sure our destination directory is writable
        if (!is_writable($migrations_dir)) {
            throw new OuzoMigrationsException("ERROR: migration directory '" . $migrations_dir . "' is not writable by the current user. Check permissions and try again.", OuzoMigrationsException::INVALID_MIGRATION_DIR);
        }

        //write it out!
        $full_path = $migrations_dir . DIRECTORY_SEPARATOR . $file_name;
        $template_str = self::get_template($class);
        $file_result = file_put_contents($full_path, $template_str);
        if ($file_result === FALSE) {
            throw new OuzoMigrationsException("Error writing to migrations directory/file. Do you have sufficient privileges?", OuzoMigrationsException::INVALID_MIGRATION_DIR);
        } else {
            $output .= "\n\tCreated migration: {$file_name}\n\n";
        }

        return $output;
    }

    public function parse_args($argv)
    {
        foreach ($argv as $i => $arg) {
            if (strpos($arg, '=') !== FALSE) {
                unset($argv[$i]);
            }
        }
        $num_args = count($argv);
        if ($num_args < 3) {
            return array();
        }
        $migration_name = $argv[2];
        return array('name' => $migration_name);
    }

    public static function classNameIsDuplicated($className, $migrationsDirs)
    {
        $migrationFiles = Migrator::get_migration_files($migrationsDirs, 'up');
        $className = strtolower($className);
        foreach ($migrationFiles as $file) {
            if (strtolower($file['class']) == $className) {
                return $file['module'];
            }
        }
        return false;
    }

    public static function get_template($klass)
    {
        $template = <<<TPL
<?php

class $klass extends Base
{
    public function up()
    {
    }//up()

    public function down()
    {
    }//down()
}

TPL;

        return $template;
    }

    public function help()
    {
        $output = <<<USAGE

\tTask: db:generate <migration name>

\tGenerator for migrations.

\t<migration name> is a descriptive name of the migration,
\tjoined with underscores. e.g.: add_index_to_users | create_users_table

\tExample :

\t\tphp {$_SERVER['argv'][0]} db:generate add_index_to_users

USAGE;

        return $output;
    }
}