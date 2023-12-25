<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkbunnyWebmanRabbitMQClean extends AbstractCommand
{
    protected static $defaultName        = 'workbunny:rabbitmq-clean';
    protected static $defaultDescription = 'Remove all workbunny/webman-rabbitmq Builders. ';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('workbunny:rabbitmq-clean')
            ->setDescription('Remove all workbunny/webman-rabbitmq Builders. ');
        $this->addOption('close', 'c', InputOption::VALUE_NONE, 'Only close mode.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $close = $input->getOption('close');
        // todo
        return self::SUCCESS;
    }
}
