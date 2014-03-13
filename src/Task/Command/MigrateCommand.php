<?php
namespace Task\Command;

use Ouzo\Config;
use OuzoMigrations\Adapter\AdapterFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Task\Db\MigrateTask;

class MigrateCommand extends Command
{
    protected function configure()
    {
        $this->setName('db:migrate')
            ->setDescription('Apply migration')
            ->addArgument('version', InputArgument::OPTIONAL, 'Specified migration to a specific version.')
            ->addArgument('env', InputArgument::OPTIONAL, 'Environment db.', 'development');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $env = $input->getArgument('env');
        $config = Config::getValue('db', $env);;

        $generateTask = new MigrateTask($input, $output);
        $generateTask->setAdapterAndMigrator(AdapterFactory::create($config));
        $generateTask->execute();
    }
} 