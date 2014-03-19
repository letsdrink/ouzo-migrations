<?php
use Ouzo\Config;
use Ouzo\Tests\Assert;
use Ouzo\Utilities\Arrays;
use Ouzo\Utilities\Path;
use OuzoMigrations\Adapter\PgSQL\AdapterPgSQL;
use OuzoMigrations\Util\MigrationFile;
use OuzoMigrations\Util\Migrator;
use Task\Db\MigrateTask;

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

    /**
     * @test
     */
    public function shouldReturnMigrations()
    {
        //given
        $migrationsDir = Path::join(OUZO_BASE_TEST, 'dummy', 'db', 'migrations', 'ouzo_migrations_test');

        //when
        $migrationFiles = Migrator::getMigrationFiles($migrationsDir, MigrateTask::MIGRATION_UP);

        //then
        $migrationFiles = Arrays::map($migrationFiles, function(MigrationFile $file){
            return $file->getFilename();
        });
        Assert::thatArray($migrationFiles)->containsExactly('001_CreateUsers.php', '003_AddIndexToBlogs.php', '20090122193325_AddNewTable.php');
    }

    protected function tearDown()
    {
        $this->_adapter->rollbackTransaction();
        parent::tearDown();
    }
}