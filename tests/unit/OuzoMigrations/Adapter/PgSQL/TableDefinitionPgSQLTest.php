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

    /**
     * @test
     */
    public function shouldCreateDllWithColumn()
    {
        //given
        $table = new TableDefinitionPgSQL($this->_adapter, 'new_table');

        //when
        $table
            ->column('name', 'text', array('null' => false))
            ->column('age', 'integer')
            ->column('street', 'string');

        //then
        $this->assertEquals(
            'CREATE TABLE "new_table" (id serial primary key, "name" text NOT NULL, "age" integer, "street" varchar(255))',
            $table->getDll()
        );
    }

    /**
     * @test
     */
    public function shouldSetDefaultValueForColumn()
    {
        //given
        $table = new TableDefinitionPgSQL($this->_adapter, 'new_table');

        //when
        $table->column('name', 'text', array('default' => 'some text', 'null' => false));

        //then
        $this->assertEquals('CREATE TABLE "new_table" (id serial primary key, "name" text DEFAULT \'some text\' NOT NULL)', $table->getDll());
    }

    /**
     * @test
     */
    public function shouldSetAnotherPrimaryKey()
    {
        //given
        $table = new TableDefinitionPgSQL($this->_adapter, 'new_table', array('id' => false));

        //when
        $table->column('uid', 'integer', array('primary_key' => true));

        //then
        $this->assertEquals('CREATE TABLE "new_table" ("uid" integer primary key)', $table->getDll());
    }

    /**
     * @test
     */
    public function shouldCreateTableInDb()
    {
        //given
        $this->_adapter->beginTransaction();
        $table = new TableDefinitionPgSQL($this->_adapter, 'new_table');
        $table
            ->column('name', 'text', array('null' => false))
            ->column('age', 'integer')
            ->column('street', 'string');

        //when
        $table->finish();

        //then
        $this->assertTrue($this->_adapter->tableExists('new_table'));
    }

    protected function tearDown()
    {
        $this->_adapter->rollbackTransaction();
        parent::tearDown();
    }
}