<?php
namespace Task\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends Command
{
    protected function configure()
    {
        $this->setName('db:generate')
            ->setDescription('Generate migration file')
            ->addArgument('migration_file_name', InputArgument::REQUIRED, 'Migration file name.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        var_dump($input->getArgument('migration_file_name'));
    }
}