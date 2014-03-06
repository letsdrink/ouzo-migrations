<?php
namespace OuzoMigrations\Task;

use OuzoMigrations\FrameworkRunner;
use OuzoMigrations\OuzoMigrationsException;

class Base
{
    /**
     * @var FrameworkRunner
     */
    private $_framework;

    /**
     * @var \OuzoMigrations\Adapter\Base
     */
    private $_adapter;

    protected $_migrationDir;

    public function __construct($adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * @return FrameworkRunner
     */
    public function get_framework()
    {
        return $this->_framework;
    }

    public function set_framework($fw)
    {
        if (!($fw instanceof \OuzoMigrations\FrameworkRunner)) {
            throw new OuzoMigrationsException('Framework must be instance of Ruckusing_FrameworkRunner!', OuzoMigrationsException::INVALID_FRAMEWORK);
        }
        $this->_framework = $fw;
    }

    public function setAdapter($adapter)
    {
        if (!($adapter instanceof \OuzoMigrations\Adapter\Base)) {
            throw new OuzoMigrationsException('Adapter must be implement Base!', OuzoMigrationsException::INVALID_ADAPTER);
        }
        $this->_adapter = $adapter;

        return $this;
    }

    /**
     * @return \OuzoMigrations\Adapter\Base
     */
    public function get_adapter()
    {
        return $this->_adapter;
    }

    public function setMigrationsDirectory($migrationDir)
    {
        $this->_migrationDir = $migrationDir;
        return $this;
    }
}