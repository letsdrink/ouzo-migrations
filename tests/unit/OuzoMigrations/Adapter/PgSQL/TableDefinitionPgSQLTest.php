<?php
use Ouzo\Config;
use OuzoMigrations\Adapter\PgSQL\AdapterPgSQL;
use OuzoMigrations\Adapter\PgSQL\TableDefinitionPgSQL;

class TableDefinitionPgSQLTest extends PHPUnit_Framework_TestCase
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
    }

    /**
     * @test
     */
    public function shouldCreateDllWithIdColumn()
    {
        //given
        $table = new TableDefinitionPgSQL($this->_adapter, 'new_table');

        //when
        $dll = $table->getDll();

        //then
        $this->assertEquals('CREATE TABLE "new_table" (id serial primary key)', $dll);
    }

    /**
     * @test
     */
    public function shouldCreateDllToCreateTableWithoutAnyColumns()
    {
        //given
        $table = new TableDefinitionPgSQL($this->_adapter, 'new_table', array('id' => false));

        //when
        $dll = $table->getDll();

        //then
        $this->assertEquals('CREATE TABLE "new_table" ()', $dll);
    }
}
 