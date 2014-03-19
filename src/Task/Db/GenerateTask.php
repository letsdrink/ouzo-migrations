<?php
namespace Task\Db;

use Ouzo\Config;
use Ouzo\Utilities\Path;
use Ouzo\Utilities\Strings;
use OuzoMigrations\OuzoMigrationsException;
use OuzoMigrations\Task\TaskInterface;
use OuzoMigrations\Util\Migrator;
use OuzoMigrations\Util\Naming;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateTask implements TaskInterface
{
    /**
     * @var OutputInterface
     */
    private $_output;
    private $_migrationFileName;
    private $_module;

    public $migrationsDir;

    function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->_migrationFileName = $input->getArgument('migration_file_name');
        $this->_module = $input->getArgument('module') ? : 'default';
        $this->_output = $output;

        $this->_className = Naming::generateMigrationClassName($this->_migrationFileName);
        $this->_fileName = Naming::generateMigrationFileName($this->_migrationFileName);

        $this->migrationsDir = Migrator::getMigrationDir($this->_module);
    }

    public function getClassName()
    {
        return $this->_className;
    }

    public function getFileName()
    {
        return $this->_fileName;
    }

    private function _writeln($message)
    {
        $this->_output->writeln($message);
    }

    public function execute()
    {
        $this->_checkAndCreateMigrationsDir();
        $this->_isMigrationExists();
        $this->_isWritableMigrationsDir();

        $this->_createMigrationFile();
    }

    private function _isMigrationExists()
    {
        $className = $this->getClassName();
        $directory = new RecursiveDirectoryIterator($this->migrationsDir);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $file) {
            $searchClass = Naming::classFromFileToName($file->getFilename());
            if (Strings::equalsIgnoreCase($searchClass, $className)) {
                throw new GenerateException("This migration name is already used in the {$this->migrationsDir} directory. Please, choose another name.", OuzoMigrationsException::INVALID_ARGUMENT);
            }
        }
    }

    private function _isWritableMigrationsDir()
    {
        if (!is_writable($this->migrationsDir)) {
            throw new GenerateException("Migration directory {$this->migrationsDir} is not writable by the current user. Check permissions and try again.", OuzoMigrationsException::INVALID_MIGRATION_DIR);
        }
    }

    private function _checkAndCreateMigrationsDir()
    {
        $migrationsDir = $this->migrationsDir;
        if (!is_dir($migrationsDir)) {
            $this->_writeln("\tMigrations directory <info>{$migrationsDir}</info> doesn't exist, attempting to create.");
            if (!mkdir($migrationsDir, 0755, true)) {
                throw new GenerateException("Unable to create migrations directory at {$migrationsDir}, check permissions.", OuzoMigrationsException::INVALID_MIGRATION_DIR);
            } else {
                $this->_writeln("\tCreated migrations dir: <info>OK</info>.");
            }
        }
    }

    private function _classStub()
    {
        $path = Path::join(__DIR__, 'stubs', 'migration_file.stub');
        $file = file_get_contents($path);
        return str_replace('{{className}}', $this->getClassName(), $file);
    }

    private function _createMigrationFile()
    {
        $fileName = $this->getFileName();

        $classStub = $this->_classStub();
        $path = Path::join($this->migrationsDir, $fileName);
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