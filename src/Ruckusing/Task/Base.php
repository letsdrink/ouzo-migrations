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

/**
 * Ruckusing_Task_Base
 *
 * @category Ruckusing
 * @package  Ruckusing_Task
 * @author   Cody Caughlan <codycaughlan % gmail . com>
 * @link      https://github.com/ruckus/ruckusing-migrations
 */
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
        if (!($fw instanceof \Ruckusing\FrameworkRunner)) {
            throw new RuckusingException(
                    'Framework must be instance of Ruckusing_FrameworkRunner!',
                    RuckusingException::INVALID_FRAMEWORK
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
