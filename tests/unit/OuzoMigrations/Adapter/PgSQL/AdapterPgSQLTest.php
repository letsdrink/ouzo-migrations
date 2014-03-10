<?php
use Ouzo\Config;
use OuzoMigrations\Adapter\PgSQL\AdapterPgSQL;

class AdapterPgSQLTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var AdapterPgSQL
     */
    private $_adapter;

    protected function setUp()
    {
        parent::setUp();
        $config = Config::getValue('db', 'pg_test');
        $this->_adapter = new AdapterPgSQL($config);
        $this->_adapter->beginTransaction();
    }

    /**
     * @test
     */
    public function shouldCreateTable()
    {
        //given
        $table = $this->_adapter->createTable('new_table');

        //when
        $table->finish();

        //then
        $isExists = $this->_adapter->tableExists('new_table');
        $this->assertTrue($isExists);
    }

    protected function tearDown()
    {
        $this->_adapter->rollbackTransaction();
        parent::tearDown();
    }
}