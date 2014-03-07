<?php
namespace Task\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Task\Db\StatusTask;

class StatusCommand extends Command
{
    protected function configure()
    {
        $this->setName('db:status')
            ->setDescription('Information about migrations')
            ->addOption('display', 'd', InputOption::VALUE_OPTIONAL, "Which migrations want to display: 'all', 'applied' or 'non-applied' - default all.", 'all');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $generateTask = new StatusTask($input, $output);
        $generateTask->execute();
    }
}