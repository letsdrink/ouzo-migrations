<?php
namespace Task\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Task\Db\GenerateTask;

class GenerateCommand extends Command
{
    protected function configure()
    {
        $this->setName('db:generate')
            ->setDescription('GenerateCommand migration file')
            ->addArgument('migration_file_name', InputArgument::REQUIRED, 'Migration file name.')
            ->addArgument('module', InputArgument::OPTIONAL, 'Module name defined in config.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrationFileName = $input->getArgument('migration_file_name');
        $module = $input->getArgument('module');

        $generateTask = new GenerateTask($output);
        $generateTask->execute(array(
            'migration_file_name' => $migrationFileName,
            'module' => $module
        ));
    }
}