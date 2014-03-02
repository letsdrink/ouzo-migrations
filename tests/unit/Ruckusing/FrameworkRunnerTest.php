<?php
use Ruckusing\FrameworkRunner;

class FrameworkRunnerTest extends PHPUnit_Framework_TestCase
{
    private $_config;

    protected function setUp()
    {
        parent::setUp();
        $this->_config = array(
            'db' => array(
                'development' => array(
                    'type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'database' => 'ruckusing_migrations',
                    'user' => 'root',
                    'password' => 'piotr0987',
                ),
                'pg_test' => array(
                    'type' => 'pgsql',
                    'host' => 'localhost',
                    'port' => 5432,
                    'database' => 'ruckusing_migrations_test',
                    'user' => 'db_user',
                    'password' => 'dbuser123',
                )
            ),
            'migrations_dir' => array('default' => RUCKUSING_WORKING_BASE . '/migrations'),
            'db_dir' => RUCKUSING_WORKING_BASE . DIRECTORY_SEPARATOR . 'db',
            'log_dir' => RUCKUSING_WORKING_BASE . DIRECTORY_SEPARATOR . 'logs',
            'ruckusing_base' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..'
        );
    }

    /**
     * @test
     * @expectedException \Ruckusing\RuckusingException
     */
    public function shouldThrowExceptionWhenNotFoundEnvInConfig()
    {
        //given
        $config = $this->_config;
        unset($config['db']['development']);

        //when
        new FrameworkRunner($config, array());
    }

    /**
     * @test
     * @expectedException \Ruckusing\RuckusingException
     */
    public function shouldThrowExceptionWhenNotFoundDbDirInConfig()
    {
        //given
        $config = $this->_config;
        unset($config['db_dir']);

        //when
        new FrameworkRunner($config, array());
    }

    /**
     * @test
     * @expectedException \Ruckusing\RuckusingException
     */
    public function shouldThrowExceptionWhenNotFoundLogDirInConfig()
    {
        //given
        $config = $this->_config;
        unset($config['log_dir']);

        //when
        new FrameworkRunner($config, array());
    }

    /**
     * @test
     * @expectedException \Ruckusing\RuckusingException
     */
    public function shouldThrowExceptionWhenNotFoundMigrationsDirInConfig()
    {
        //given
        $config = $this->_config;
        unset($config['migrations_dir']);

        //when
        new FrameworkRunner($config, array());
    }

    /**
     * @test
     * @expectedException \Ruckusing\RuckusingException
     */
    public function shouldThrowExceptionWhenNotFoundMigrationsTaskOptionsInConfig()
    {
        //when
        new FrameworkRunner($this->_config, array('db:trololo'));
    }
}