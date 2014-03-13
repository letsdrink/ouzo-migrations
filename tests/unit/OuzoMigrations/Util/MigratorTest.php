<?php
use Ouzo\Config;
use OuzoMigrations\Adapter\PgSQL\AdapterPgSQL;
use OuzoMigrations\Util\Migrator;

class MigratorTest extends PHPUnit_Framework_TestCase
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
    public function shouldGetCurrentVersion()
    {
        //given
        $this->_adapter->createSchemaVersionTable();
        $this->_adapter->query("INSERT INTO schema_migrations (version) values ('123')");
        $this->_adapter->query("INSERT INTO schema_migrations (version) values ('23')");
        $this->_adapter->query("INSERT INTO schema_migrations (version) values ('1')");
        $migrator = new Migrator($this->_adapter);

        //when
        $currentVersion = $migrator->getMaxVersion();

        //then
        $this->assertEquals('123', $currentVersion);
    }

    protected function tearDown()
    {
        $this->_adapter->rollbackTransaction();
        parent::tearDown();
    }
}