<?php
namespace Ruckusing\Task;
/**
 * Ruckusing
 *
 * @category  Ruckusing
 * @package   Ruckusing_Task
 * @author    Cody Caughlan <codycaughlan % gmail . com>
 * @link      https://github.com/ruckus/ruckusing-migrations
 */

use Ruckusing\RuckusingException;

define('RUCKUSING_TASK_DIR', RUCKUSING_BASE . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Task');

/**
 * Ruckusing_Task_Manager
 *
 * @category Ruckusing
 * @package  Ruckusing_Task
 * @author   Cody Caughlan <codycaughlan % gmail . com>
 * @link      https://github.com/ruckus/ruckusing-migrations
 */
class Manager
{
    /**
     * adapter
     *
     * @var Base
     */
    private $_adapter;

    /**
     * tasks
     *
     * @var array
     */
    private $_tasks = array();

    /**
     * Creates an instance of Ruckusing_Task_Manager
     *
     * @param Ruckusing_Adpater_Base $adapter The current adapter being used
     *
     * @return Ruckusing_Task_Manager
     */
    public function __construct($adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * set adapter
     *
     * @param Base $adapter the current adapter
     *
     * @return Ruckusing_Util_Migrator
     */
    public function setAdapter($adapter)
    {
        if (!($adapter instanceof \Ruckusing\Adapter\Base)) {
            throw new RuckusingException(
                    'Adapter must be implement Base!',
                    RuckusingException::INVALID_ADAPTER
            );
        }
        $this->_adapter = $adapter;

        return $this;
    }

    /**
     * Get the current adapter
     *
     * @return object $adapter The current adapter being used
     */
    public function get_adapter()
    {
        return $this->_adapter;
    }

    /**
     * Searches for the given task, and if found
     * returns it. Otherwise null is returned.
     *
     * @param string $key The task name
     *
     * @return object | null
     */
    public function get_task($key)
    {
        if (!$this->has_task($key)) {
            throw new RuckusingException(
                    "Task '$key' is not registered.",
                    RuckusingException::INVALID_ARGUMENT
            );
        }

        return $this->_tasks[$key];
    }

    /**
     * Check if a task exists
     *
     * @param string $key The task name
     *
     * @return boolean
     */
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

    /**
     * Register a new task name under the specified key.
     * $obj is a class which implements the ITask interface
     * and has an execute() method defined.
     *
     * @param string $key the task name
     * @param object $obj the task object
     *
     * @return boolean
     */
    public function register_task($key, $obj)
    {
        if (array_key_exists($key, $this->_tasks)) {
            throw new RuckusingException(
                    sprintf("Task key '%s' is already defined!", $key),
                    RuckusingException::INVALID_ARGUMENT
            );

            return false;
        }

        if (!($obj instanceof Ruckusing_Task_Interface)) {
            throw new RuckusingException(
                    sprintf('Task (' . $key . ') does not implement Ruckusing_Task_Interface', $key),
                    RuckusingException::INVALID_ARGUMENT
            );

            return false;
        }
        $this->_tasks[$key] = $obj;

        return true;
    }

    //---------------------
    // PRIVATE METHODS
    //---------------------
    /**
    * Load all taks
    *
    * @param string $task_dir the task dir path
    */
    private function load_all_tasks($task_dir)
    {
        if (!is_dir($task_dir)) {
            throw new RuckusingException(
                    sprintf("Task dir: %s does not exist", $task_dir),
                    RuckusingException::INVALID_ARGUMENT
            );

            return false;
        }
        $namespaces = scandir($task_dir);
        foreach ($namespaces as $namespace) {
            if ($namespace == '.' || $namespace == '..'
                    || ! is_dir($task_dir . DIRECTORY_SEPARATOR . $namespace)
            ) {
                continue;
            }
            $files = scandir($task_dir . DIRECTORY_SEPARATOR . $namespace);
            $regex = '/^(\w+)\.php$/';
            foreach ($files as $file) {
                //skip over invalid files
                if ($file == '.' || $file == ".." || !preg_match($regex, $file, $matches) ) {
                    continue;
                }
                require_once $task_dir . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR . $file;
                $klass = Ruckusing_Util_Naming::class_from_file_name($task_dir . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR . $file);
                $task_name = Ruckusing_Util_Naming::task_from_class_name($klass);

                $this->register_task($task_name, new $klass($this->get_adapter()));
            }
        }
    }

    /**
     * Execute a task
     *
     * @param object $framework The current framework
     * @param string $task_name the task to execute
     * @param array  $options
     *
     * @return boolean
     */
    public function execute($framework, $task_name, $options)
    {
        $task = $this->get_task($task_name);
        $task->set_framework($framework);

        return $task->execute($options);
    }

    /**
     * Get display help of task
     *
     * @param string $task_name The task name
     *
     * @return string
     */
    public function help($task_name)
    {
        $task = $this->get_task($task_name);

        return $task->help();
    }

}
