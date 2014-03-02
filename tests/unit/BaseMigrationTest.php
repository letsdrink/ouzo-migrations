<?php
use OuzoMigrations\Util\Logger;

class BaseMigrationTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $ruckusing_config = require RUCKUSING_BASE . '/config/database.inc.php';

        if (!is_array($ruckusing_config) || !(array_key_exists("db", $ruckusing_config) && array_key_exists("mysql_test", $ruckusing_config['db']))) {
            die("\n'mysql_test' DB is not defined in config/database.inc.php\n\n");
            //$this->markTestSkipped
        }

        $test_db = $ruckusing_config['db']['mysql_test'];

        //setup our log
        $logger = Logger::instance(RUCKUSING_BASE . '/tests/logs/test.log');

        $this->adapter = new \OuzoMigrations\Adapter\MySQL\Base($test_db, $logger);
        $this->adapter->logger->log("Test run started: " . date('Y-m-d g:ia T'));
    }

    protected function tearDown()
    {
        //delete any tables we created
        if ($this->adapter->has_table('users', true)) {
            $this->adapter->drop_table('users');
        }

        if ($this->adapter->has_table(RUCKUSING_TS_SCHEMA_TBL_NAME, true)) {
            $this->adapter->drop_table(RUCKUSING_TS_SCHEMA_TBL_NAME);
        }
    }

    public function test_can_create_index_with_custom_name()
    {
        //create it
        $this->adapter->execute_ddl("CREATE TABLE `users` ( name varchar(20), age int(3) );");
        $this->adapter->add_index("users", "name", array('name' => 'my_special_index'));

        //ensure it exists
        $this->assertEquals(true, $this->adapter->has_index("users", "name", array('name' => 'my_special_index')));

        //drop it
        $this->adapter->remove_index("users", "name", array('name' => 'my_special_index'));
        $this->assertEquals(false, $this->adapter->has_index("users", "my_special_index"));
    }
}