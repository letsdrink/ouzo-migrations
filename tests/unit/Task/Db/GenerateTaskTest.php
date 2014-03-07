<?php
use org\bovigo\vfs\vfsStream;
use Ouzo\Tests\CatchException;
use Ouzo\Utilities\DeleteDirectory;
use Ouzo\Utilities\Path;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\StreamOutput;
use Task\Db\GenerateTask;

class GenerateTaskTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldThrowExceptionWhenMigrationsDirUnableToCreate()
    {
        //given
        vfsStream::setup('migrations_dir');

        $definition = new InputDefinition(array(new InputArgument('migration_file_name'), new InputArgument('module', InputArgument::OPTIONAL)));
        $input = new ArrayInput(array('migration_file_name' => 'test', 'module' => null), $definition);
        $output = new StreamOutput(fopen('php://memory', 'w', false));
        $generateTask = new GenerateTask($input, $output);
        $generateTask->migrationsDir = vfsStream::url('no_create_dir');

        //when
        CatchException::when($generateTask)->execute();

        //then
        CatchException::assertThat()->isInstanceOf('\Task\Db\GenerateException');
    }

    /**
     * @test
     */
    public function shouldThrowExceptionWhenMigrationAlreadyExists()
    {
        //given
        $definition = new InputDefinition(array(new InputArgument('migration_file_name'), new InputArgument('module', InputArgument::OPTIONAL)));
        $input = new ArrayInput(array('migration_file_name' => 'test', 'module' => null), $definition);
        $output = new StreamOutput(fopen('php://memory', 'w', false));
        $generateTask = new GenerateTask($input, $output);
        $generateTask->migrationsDir = Path::joinWithTemp('migrations_dir');

        $generateTask->execute();

        //when
        CatchException::when($generateTask)->execute();

        //then
        CatchException::assertThat()->isInstanceOf('\Task\Db\GenerateException');
        DeleteDirectory::recursive($generateTask->migrationsDir);
    }

    /**
     * @test
     */
    public function shouldCreateDirIfNotExists()
    {
        //given
        $definition = new InputDefinition(array(new InputArgument('migration_file_name'), new InputArgument('module', InputArgument::OPTIONAL)));
        $input = new ArrayInput(array('migration_file_name' => 'test', 'module' => null), $definition);
        $output = new StreamOutput(fopen('php://output', 'w', false));
        $generateTask = new GenerateTask($input, $output);
        $generateTask->migrationsDir = Path::joinWithTemp('migrations_dir');

        //when
        $generateTask->execute();

        //then
        $expected = <<<OUTPUT
	#Migrations directory /tmp/migrations_dir doesn't exist, attempting to create\.
	Created migrations dir: OK\.
	Created migration: .*\.php\.#
OUTPUT;
        $this->expectOutputRegex($expected);
        $this->assertFileExists($generateTask->migrationsDir);
        DeleteDirectory::recursive($generateTask->migrationsDir);
    }

    /**
     * @test
     */
    public function shouldCreateProperlyGeneratedMigrationClass()
    {
        //given
        $definition = new InputDefinition(array(new InputArgument('migration_file_name'), new InputArgument('module', InputArgument::OPTIONAL)));
        $input = new ArrayInput(array('migration_file_name' => 'test', 'module' => null), $definition);
        $output = new StreamOutput(fopen('php://memory', 'w', false));
        $generateTask = new GenerateTask($input, $output);
        $generateTask->migrationsDir = Path::joinWithTemp('migrations_dir');

        //when
        $generateTask->execute();

        //then
        $expeced = <<<TEMPLATE
<?php
class {$generateTask->getClassName()}
{
    public function up()
    {

    }

    public function down()
    {

    }
}
TEMPLATE;
        $actual = file_get_contents(Path::join($generateTask->migrationsDir, $generateTask->getFileName()));
        $this->assertEquals($expeced, $actual);
        DeleteDirectory::recursive($generateTask->migrationsDir);
    }
}