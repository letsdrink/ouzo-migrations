<?php
namespace Task\Db;

use Ouzo\Config;
use Ouzo\Utilities\Arrays;
use Ouzo\Utilities\Path;
use Ouzo\Utilities\Strings;
use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Task\TaskInterface;
use OuzoMigrations\Util\Naming;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateTask implements TaskInterface
{
    /**
     * @var OutputInterface
     */
    private $_output;

    function __construct(OutputInterface $output)
    {
        $this->_output = $output;
    }

    private function _writeln($message)
    {
        $this->_output->writeln($message);
    }

    public function execute(array $arguments)
    {
        $migrationFileName = Arrays::getValue($arguments, 'migration_file_name');
        $module = Arrays::getValue($arguments, 'module', 'default');
        $className = Naming::generateMigrationClassName($migrationFileName);
        $fileName = Naming::generateMigrationFileName($migrationFileName);
        $migrationsDir = Config::getValue('migrations_dir', $module);

        $this->_checkAndCreateMigrationsDir($migrationsDir);
        $this->_isMigrationExists($className, $migrationsDir);
        $this->_isWritableMigrationsDir($migrationsDir);

        $this->_createMigrationFile($className, $migrationsDir, $fileName);
    }

    private function _isMigrationExists($className, $migrationsDir)
    {
        $directory = new RecursiveDirectoryIterator($migrationsDir);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $file) {
            $searchClass = Naming::classFromFileToName($file->getFilename());
            if (Strings::equalsIgnoreCase($searchClass, $className)) {
                throw new GenerateException("This migration name is already used in the {$migrationsDir} directory. Please, choose another name.", OuzoMigrationsException::INVALID_ARGUMENT);
            }
        }
    }

    private function _isWritableMigrationsDir($migrationsDir)
    {
        if (!is_writable($migrationsDir)) {
            throw new GenerateException("Migration directory {$migrationsDir} is not writable by the current user. Check permissions and try again.", OuzoMigrationsException::INVALID_MIGRATION_DIR);
        }
    }

    private function _checkAndCreateMigrationsDir($migrationsDir)
    {
        if (!is_dir($migrationsDir)) {
            $this->_writeln("\tMigrations directory <info>{$migrationsDir}</info> doesn't exist, attempting to create.");
            if (mkdir($migrationsDir, 0755, true) === FALSE) {
                $this->_writeln("\t<error>Unable to create migrations directory at {$migrationsDir}, check permissions?</error>");
            } else {
                $this->_writeln("\tCreated migrations dir: <info>OK</info>.");
            }
        }
    }

    private function _classStub($className)
    {
        $path = Path::join(__DIR__, 'stubs', 'migration_file.stub');
        $file = file_get_contents($path);
        return str_replace('{{className}}', $className, $file);
    }

    private function _createMigrationFile($className, $migrationsDir, $fileName)
    {
        $classStub = $this->_classStub($className);
        $path = Path::join($migrationsDir, $fileName);
        if (!file_put_contents($path, $classStub)) {
            throw new GenerateException("Error writing to migrations directory/file. Do you have sufficient privileges?", OuzoMigrationsException::INVALID_MIGRATION_DIR);
        } else {
            $this->_writeln("\tCreated migration: <info>{$fileName}</info>.");
        }
    }
}

class GenerateException extends OuzoMigrationsException
{
}