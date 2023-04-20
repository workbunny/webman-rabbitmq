<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Workbunny\WebmanRabbitMQ\config;
use function Workbunny\WebmanRabbitMQ\config_path;
use function Workbunny\WebmanRabbitMQ\base_path;
use function Workbunny\WebmanRabbitMQ\is_empty_dir;

class WorkbunnyWebmanRabbitMQClean extends AbstractCommand
{
    protected static $defaultName        = 'workbunny:rabbitmq-clean';
    protected static $defaultDescription = 'Remove all workbunny/webman-rabbitmq Builders. ';

    /**
     * @return void
     */
    protected function configure(): void
    {
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
