<?php
namespace OuzoMigrations\Task;

use OuzoMigrations\OuzoMigrationsException;

class Base
{
    /**
     * the framework
     *
     * @var Ruckusing_FrameworkRunner
     */
    private $_framework;

    /**
     * the adapter
     *
     * @var Base
     */
    private $_adapter;

    /**
     * the migration directory
     *
     * @var string
     */
    protected $_migrationDir;

    /**
     * Creates an instance of Ruckusing_Task_Base
     *
     * @param Base $adapter The current adapter being used
     *
     * @return Ruckusing_Task_Base
     */
    public function __construct($adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * Get the current framework
     *
     * @return object
     */
    public function get_framework()
    {
        return $this->_framework;
    }

    /**
     * Set the current framework
     *
     * @param Ruckusing_FrameworkRunner $fw the framework being set
     */
    public function set_framework($fw)
    {
        if (!($fw instanceof \OuzoMigrations\FrameworkRunner)) {
            throw new OuzoMigrationsException(
                    'Framework must be instance of Ruckusing_FrameworkRunner!',
                    OuzoMigrationsException::INVALID_FRAMEWORK
            );
        }
        $this->_framework = $fw;
    }

    /**
     * set adapter
     *
     * @param Base $adapter the current adapter
     *
     * @return Ruckusing_Task_Base
     */
    public function setAdapter($adapter)
    {
        if (!($adapter instanceof \OuzoMigrations\Adapter\Base)) {
            throw new OuzoMigrationsException(
                    'Adapter must be implement Base!',
                    OuzoMigrationsException::INVALID_ADAPTER
            );
        }
        $this->_adapter = $adapter;

        return $this;
    }

    /**
     * Get the current adapter
     *
     * @return object
     */
    public function get_adapter()
    {
        return $this->_adapter;
    }

    /**
     * set migration directories
     *
     * @param string $migrationDir Directory of migrations
     *
     * @return Ruckusing_Task_Base
     */
    public function setMigrationsDirectory($migrationDir)
    {
        $this->_migrationDir = $migrationDir;

        return $this;
    }

}