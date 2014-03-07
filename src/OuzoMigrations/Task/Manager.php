<?php
namespace OuzoMigrations\Task;

use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Util\Naming;

define('RUCKUSING_TASK_DIR', RUCKUSING_BASE . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Task');

class Manager
{
    /**
     * @var \OuzoMigrations\Adapter\AdapterBase
     */
    private $_adapter;

    private $_tasks = array();

    public function __construct($adapter)
    {
        $this->setAdapter($adapter);
    }

    public function setAdapter($adapter)
    {
        if (!($adapter instanceof \OuzoMigrations\Adapter\AdapterBase)) {
            throw new OuzoMigrationsException('Adapter must be implement Base!', OuzoMigrationsException::INVALID_ADAPTER);
        }
        $this->_adapter = $adapter;

        return $this;
    }

    public function get_adapter()
    {
        return $this->_adapter;
    }

    /**
     * @param $key
     * @return mixed
     * @throws \OuzoMigrations\OuzoMigrationsException
     * @return TaskInterface
     */
    public function get_task($key)
    {
        if (!$this->has_task($key)) {
            throw new OuzoMigrationsException("Task '$key' is not registered.", OuzoMigrationsException::INVALID_ARGUMENT);
        }
        return $this->_tasks[$key];
    }

    public function has_task($key)
    {
        if (empty($this->_tasks)) {
            $this->load_all_tasks(RUCKUSING_TASK_DIR);
        }
        if (array_key_exists($key, $this->_tasks)) {
            return true;
        }
        return false;
    }

    public function register_task($key, $obj)
    {
        if (array_key_exists($key, $this->_tasks)) {
            throw new OuzoMigrationsException(sprintf("Task key '%s' is already defined!", $key), OuzoMigrationsException::INVALID_ARGUMENT);
        }
        if (!($obj instanceof TaskInterface)) {
            throw new OuzoMigrationsException(sprintf('Task (' . $key . ') does not implement Ruckusing_Task_Interface', $key), OuzoMigrationsException::INVALID_ARGUMENT);
        }
        $this->_tasks[$key] = $obj;
        return true;
    }

    private function load_all_tasks($task_dir)
    {
        if (!is_dir($task_dir)) {
            throw new OuzoMigrationsException(sprintf("Task dir: %s does not exist", $task_dir), OuzoMigrationsException::INVALID_ARGUMENT);
        }
        $namespaces = scandir($task_dir);
        foreach ($namespaces as $namespace) {
            if ($namespace == '.' || $namespace == '..'
                || !is_dir($task_dir . DIRECTORY_SEPARATOR . $namespace)
            ) {
                continue;
            }
            $files = scandir($task_dir . DIRECTORY_SEPARATOR . $namespace);
            $regex = '/^(\w+)\.php$/';
            foreach ($files as $file) {
                //skip over invalid files
                if ($file == '.' || $file == ".." || !preg_match($regex, $file)) {
                    continue;
                }
                require_once $task_dir . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR . $file;
                $className = Naming::classFromFileToName($task_dir . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR . $file);
                $task_name = Naming::task_from_class_name($className);

                $this->register_task($task_name, new $className($this->get_adapter()));
            }
        }
    }

    public function execute($framework, $task_name, $options)
    {
        $task = $this->get_task($task_name);
        $task->set_framework($framework);
        return $task->execute($options);
    }

    public function help($task_name)
    {
        $task = $this->get_task($task_name);
        return $task->help();
    }
}