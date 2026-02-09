<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;

class WorkbunnyWebmanRabbitMQBuilder extends AbstractCommand
{
    protected static $defaultName = 'workbunny:rabbitmq-builder';
    protected static $defaultDescription = 'Create and initialize a workbunny/webman-rabbitmq Builder. ';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('workbunny:rabbitmq-builder')
            ->setDescription('Create and initialize a workbunny/webman-rabbitmq Builder. ');
        $this->addArgument('name', InputArgument::REQUIRED, 'Builder name. ');
        $this->addArgument('count', InputArgument::OPTIONAL, 'Number of processes started by builder. ', 1);
        $this->addOption('mode', 'm', InputOption::VALUE_REQUIRED, 'Builder mode: queue or your custom mode.', 'queue');
        $this->addOption('delayed', 'd', InputOption::VALUE_NONE, 'Delay mode builder. ');
        $this->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Connection name.', 'default');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $count = $input->getArgument('count');
        $delayed = $input->getOption('delayed');
        $mode = $input->getOption('mode');
        $connection = $input->getOption('connection');
        list($name, $namespace, $file) = $this->getFileInfo($name, $delayed);
        // check process.php
        if (!file_exists($process = config_path() . '/plugin/workbunny/webman-rabbitmq/process.php')) {
            return $this->error($output, "Builder {$name} failed to create: plugin/workbunny/webman-rabbitmq/process.php does not exist.");
        }
        // check config
        $config = config('plugin.workbunny.webman-rabbitmq.process', []);
        $processName = str_replace('\\', '.', $className = "$namespace\\$name");
        if (isset($config[$processName])) {
            return $this->error($output, "Builder {$name} failed to create: Config already exists.");
        }
        // get mode
        if (!$builderClass = AbstractBuilder::getMode($mode)) {
            return $this->error($output, "Builder {$name} failed to create: Mode {$mode} does not exist.");
        }
        // config set
        if (file_put_contents($process, preg_replace_callback(
            '/(];)(?!.*\1)/',
            function () use ($processName, $className, $count, $mode) {
                return <<<DOC
    '$processName' => [
        'handler' => \\$className::class,
        'count'   => {$count},
        'mode'    => '$mode',
    ],
];
DOC;
            },
            file_get_contents($process),
            1
        )) !== false) {
            $this->info($output, 'Config updated.');
        }
        // dir create
        if (!is_dir($path = pathinfo($file, PATHINFO_DIRNAME))) {
            mkdir($path, 0777, true);
        }
        // file create
        if (!file_exists($file)) {
            if (file_put_contents($file, $builderClass::classContent(
                $namespace,
                $name,
                str_ends_with($name, 'Delayed'),
                $connection
            )) !== false) {
                $this->info($output, 'Builder created.');
            }
        }

        return $this->success($output, "Builder {$name} created successfully.");
    }
}
