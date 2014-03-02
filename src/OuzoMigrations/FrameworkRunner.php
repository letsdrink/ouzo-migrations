<?php
namespace OuzoMigrations;

use Ouzo\Utilities\Arrays;
use OuzoMigrations\Task\Manager;
use OuzoMigrations\Util\Logger;

class FrameworkRunner
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var Adapter\Base
     */

    private $_adapter = null;
    /**
     * @var Manager
     */
    private $_task_mgr = null;

    private $_config = array();

    private $_cur_task_name = "";

    private $_task_options = "";

    private $_env = "development";

    private $_showHelp = false;

    public function __construct($config, $argv)
    {
        set_error_handler(array('\OuzoMigrations\OuzoMigrationsException', 'errorHandler'), E_ALL);
        set_exception_handler(array('\OuzoMigrations\OuzoMigrationsException', 'exceptionHandler'));

        $this->_config = $config;

        $this->parse_args($argv);

        $this->_verifyDbConfig();

        $this->initialize_logger();

        $this->load_all_adapters(RUCKUSING_BASE . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'OuzoMigrations' . DIRECTORY_SEPARATOR . 'Adapter');

        $this->initialize_db();

        $this->init_tasks();

    }

    public function execute()
    {
        $output = '';
        if (empty($this->_cur_task_name)) {
            if (isset($_SERVER["argv"][1]) && stripos($_SERVER["argv"][1], '=') === false) {
                $output .= sprintf("\n\tWrong Task format: %s\n", $_SERVER["argv"][1]);
            }
            $output .= $this->help();
        } else {
            if ($this->_task_mgr->has_task($this->_cur_task_name)) {
                if ($this->_showHelp) {
                    $output .= $this->_task_mgr->help($this->_cur_task_name);
                } else {
                    $output .= $this->_task_mgr->execute($this, $this->_cur_task_name, $this->_task_options);

                }
            } else {
                $output .= sprintf("\n\tTask not found: %s\n", $this->_cur_task_name);
                $output .= $this->help();
            }
        }

        if ($this->logger) {
            $this->logger->close();
        }

        return $output;
    }

    /**
     * @return Adapter\Base
     */
    public function get_adapter()
    {
        return $this->_adapter;
    }

    public function init_tasks()
    {
        $this->_task_mgr = new Manager($this->_adapter);
    }

    public function migrations_directory($key = '')
    {
        if ($key) {
            if (!isset($this->_config['migrations_dir'][$key])) {
                throw new OuzoMigrationsException(
                    sprintf("No module %s migration_dir set in config", $key),
                    OuzoMigrationsException::INVALID_CONFIG
                );
            }
            $migration_dir = $this->_config['migrations_dir'][$key] . DIRECTORY_SEPARATOR;
        } elseif (is_array($this->_config['migrations_dir'])) {
            $migration_dir = $this->_config['migrations_dir']['default'] . DIRECTORY_SEPARATOR;
        } else {
            $migration_dir = $this->_config['migrations_dir'] . DIRECTORY_SEPARATOR;
        }

        if (array_key_exists('directory', $this->_config['db'][$this->_env])) {
            return $migration_dir . $this->_config['db'][$this->_env]['directory'];
        }

        return $migration_dir . $this->_config['db'][$this->_env]['database'];
    }

    public function migrations_directories()
    {
        $folder = $this->_config['db'][$this->_env]['database'];
        if (array_key_exists('directory', $this->_config['db'][$this->_env])) {
            $folder = $this->_config['db'][$this->_env]['directory'];
        }

        $result = array();
        if (is_array($this->_config['migrations_dir'])) {
            foreach ($this->_config['migrations_dir'] as $name => $path) {
                $result[$name] = $path . DIRECTORY_SEPARATOR . $folder;
            }
        } else {
            $result['default'] = $this->_config['migrations_dir'] . DIRECTORY_SEPARATOR . $folder;
        }

        return $result;
    }

    public function db_directory()
    {
        $path = $this->_config['db_dir'] . DIRECTORY_SEPARATOR;

        if (array_key_exists('directory', $this->_config['db'][$this->_env])) {
            return $path . $this->_config['db'][$this->_env]['directory'];
        }

        return $path . $this->_config['db'][$this->_env]['database'];
    }

    public function initialize_db()
    {
        $db = $this->_config['db'][$this->_env];
        $adapter = $this->get_adapter_class($db['type']);

        if (empty($adapter)) {
            throw new OuzoMigrationsException(
                sprintf("No adapter available for DB type: %s", $db['type']),
                OuzoMigrationsException::INVALID_ADAPTER
            );
        }
        //construct our adapter
        $this->_adapter = new $adapter($db, $this->logger);

    }

    public function initialize_logger()
    {
        if (is_dir($this->_config['log_dir']) && !is_writable($this->_config['log_dir'])) {
            throw new OuzoMigrationsException(
                "\n\nCannot write to log directory: " . $this->_config['log_dir'] . "\n\nCheck permissions.\n\n",
                OuzoMigrationsException::INVALID_LOG
            );
        } elseif (!is_dir($this->_config['log_dir'])) {
            //try and create the log directory
            mkdir($this->_config['log_dir'], 0755, true);
        }
        $log_name = sprintf("%s.log", $this->_env);
        $this->logger = Logger::instance($this->_config['log_dir'] . DIRECTORY_SEPARATOR . $log_name);
    }

    private function parse_args($argv)
    {
        $num_args = count($argv);

        $options = array();
        for ($i = 0; $i < $num_args; $i++) {
            $arg = $argv[$i];
            if (stripos($arg, ':') !== false) {
                $this->_cur_task_name = $arg;
            } elseif ($arg == 'help') {
                $this->_showHelp = true;
                continue;
            } elseif (stripos($arg, '=') !== false) {
                list($key, $value) = explode('=', $arg);
                $key = strtolower($key); // Allow both upper and lower case parameters
                $options[$key] = $value;
                if ($key == 'env') {
                    $this->_env = $value;
                }
            }
        }
        $this->_task_options = $options;
    }

    public function update_schema_for_timestamps()
    {
        //only create the table if it doesnt already exist
        $this->_adapter->create_schema_version_table();
        //insert all existing records into our new table
        $migrator_util = new Ruckusing_Util_Migrator($this->_adapter);
        $files = $migrator_util->get_migration_files($this->migrations_directories(), 'up');
        foreach ($files as $file) {
            if ((int)$file['version'] >= PHP_INT_MAX) {
                //its new style like '20081010170207' so its not a candidate
                continue;
            }
            //query old table, if it less than or equal to our max version, then its a candidate for insertion
            $query_sql = sprintf("SELECT version FROM %s WHERE version >= %d", RUCKUSING_SCHEMA_TBL_NAME, $file['version']);
            $existing_version_old_style = $this->_adapter->select_one($query_sql);
            if (count($existing_version_old_style) > 0) {
                //make sure it doesnt exist in our new table, who knows how it got inserted?
                $new_vers_sql = sprintf("SELECT version FROM %s WHERE version = %d", RUCKUSING_TS_SCHEMA_TBL_NAME, $file['version']);
                $existing_version_new_style = $this->_adapter->select_one($new_vers_sql);
                if (empty($existing_version_new_style)) {
                    // use sprintf & %d to force it to be stripped of any leading zeros, we *know* this represents an old version style
                    // so we dont have to worry about PHP and integer overflow
                    $insert_sql = sprintf("INSERT INTO %s (version) VALUES (%d)", RUCKUSING_TS_SCHEMA_TBL_NAME, $file['version']);
                    $this->_adapter->query($insert_sql);
                }
            }
        }
    }

    private function _verifyDbConfig()
    {
        $this->_checkEnvConfig();

        $this->_checkConfigParameter('db_dir');
        $this->_checkConfigParameter('log_dir');
        $this->_checkConfigParameter('migrations_dir');
        $this->_checkDirConfigMigrations();

        $this->_checkTaskConfig();

        $this->_checkDbConfigParameter('type');
        $this->_checkDbConfigParameter('host');
        $this->_checkDbConfigParameter('database');
        $this->_checkDbConfigParameter('user');
        $this->_checkDbConfigParameter('password');
    }

    private function _checkEnvConfig()
    {
        $db_config = $this->_config['db'];
        if (!Arrays::keyExists($db_config, $this->_env)) {
            throw new OuzoMigrationsException(sprintf("Error: env '%s' is not set DB", $this->_env), OuzoMigrationsException::INVALID_CONFIG);
        }
    }

    private function _checkDbConfigParameter($key)
    {
        $db_config = $this->_config['db'][$this->_env];
        if (!Arrays::keyExists($db_config, $key)) {
            throw new OuzoMigrationsException(sprintf("Error: '%s' is not set for '%s' DB", $key, $this->_env), OuzoMigrationsException::INVALID_CONFIG);
        }
    }

    private function _checkConfigParameter($key)
    {
        $db_config = $this->_config;
        if (!Arrays::keyExists($db_config, $key)) {
            throw new OuzoMigrationsException(sprintf("Error: '%s' is not set in config", $key), OuzoMigrationsException::INVALID_CONFIG);
        }
    }

    private function _checkDirConfigMigrations()
    {
        if (is_array($this->_config['migrations_dir'])) {
            if (!isset($this->_config['migrations_dir']['default'])) {
                throw new OuzoMigrationsException("Error: 'migrations_dir' 'default' key is not set in config.", OuzoMigrationsException::INVALID_CONFIG);
            } elseif (empty($this->_config['migrations_dir']['default'])) {
                throw new OuzoMigrationsException("Error: 'migrations_dir' 'default' key is empty in config.", OuzoMigrationsException::INVALID_CONFIG);
            }
        }
    }

    private function _checkTaskConfig()
    {
        if (isset($this->_task_options['module']) && !isset($this->_config['migrations_dir'][$this->_task_options['module']])) {
            throw new OuzoMigrationsException(sprintf("Error: module name %s is not set in 'migrations_dir' option in config.", $this->_task_options['module']), OuzoMigrationsException::INVALID_CONFIG);
        }
    }

    private function get_adapter_class($db_type)
    {
        $adapter_class = null;
        switch ($db_type) {
            case 'mysql':
                $adapter_class = "\\OuzoMigrations\\Adapter\\MySQL\\Base";
                break;
            case 'mssql':
                $adapter_class = "\\OuzoMigrations\\Adapter\\MSSQL\\Base";
                break;
            case 'pgsql':
                $adapter_class = "\\OuzoMigrations\\Adapter\\PgSQL\\Base";
                break;
            case 'sqlite':
                $adapter_class = "\\OuzoMigrations\\Adapter\\Sqlite3\\Base";
                break;
        }
        return $adapter_class;
    }

    private function load_all_adapters($adapter_dir)
    {
        if (!is_dir($adapter_dir)) {
            throw new OuzoMigrationsException(sprintf("Adapter dir: %s does not exist", $adapter_dir), OuzoMigrationsException::INVALID_ADAPTER);
        }
        $files = scandir($adapter_dir);
        foreach ($files as $f) {
            if ($f == '.' || $f == ".." || !is_dir($adapter_dir . DIRECTORY_SEPARATOR . $f)) {
                continue;
            }
            $adapter_class_path = $adapter_dir . DIRECTORY_SEPARATOR . $f . DIRECTORY_SEPARATOR . 'Base.php';
            if (file_exists($adapter_class_path)) {
                require_once $adapter_class_path;
            }
        }
    }

    public function help()
    {
        // TODO: dynamically list all available tasks
        $output = <<<USAGE

\tUsage: php {$_SERVER['argv'][0]} <task> [help] [task parameters] [env=environment]

\thelp: Display this message

\tenv: The env command line parameter can be used to specify a different
\tdatabase to run against, as specific in the configuration file
\t(config/database.inc.php).
\tBy default, env is "development"

\ttask: In a nutshell, task names are pseudo-namespaced. The tasks that come
\twith the framework are namespaced to "db" (e.g. the tasks are "db:migrate",
\t"db:setup", etc).
\tAll tasks available actually :

\t- db:setup : A basic task to initialize your DB for migrations is
\tavailable. One should always run this task when first starting out.

\t- db:generate : A generic task which acts as a Generator for migrations.

\t- db:migrate : The primary purpose of the framework is to run migrations,
\tand the execution of migrations is all handled by just a regular ol' task.

\t- db:version : It is always possible to ask the framework (really the DB)
\twhat version it is currently at.

\t- db:status : With this taks you'll get an overview of the already
\texecuted migrations and which will be executed when running db:migrate

\t- db:schema : It can be beneficial to get a dump of the DB in raw SQL
\tformat which represents the current version.

USAGE;
        return $output;
    }
}