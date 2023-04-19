<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use function Workbunny\WebmanRabbitMQ\config;
use function Workbunny\WebmanRabbitMQ\config_path;

class WorkbunnyWebmanRabbitMQBuilder extends AbstractCommand
{
    protected static $defaultName        = 'workbunny:rabbitmq-builder';
    protected static $defaultDescription = 'Create and initialize a workbunny/webman-rabbitmq Builder. ';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Builder name. ');
        $this->addArgument('count', InputArgument::OPTIONAL, 'Number of processes started by builder. ', 1);
        $this->addOption('mode', 'm', InputOption::VALUE_REQUIRED, 'Builder mode: queue, rpc', 'queue');
        $this->addOption('delayed', 'd', InputOption::VALUE_NONE, 'Delay mode builder. ');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name    = $input->getArgument('name');
        $count   = $input->getArgument('count');
        $delayed = $input->getOption('delayed');
        $mode    = $input->getOption('mode');
        list($name, $namespace, $file) = $this->getFileInfo($name, $delayed);
        if(!file_exists($process = config_path() . '/plugin/workbunny/webman-rabbitmq/process.php')) {
            return $this->error($output, "Builder {$name} failed to create: plugin/workbunny/webman-rabbitmq/process.php does not exist.");
        }
        $processConfig = file_get_contents($process);
        $config = config('plugin.workbunny.webman-rabbitmq.process', []);
        $processName = str_replace('\\', '.', $className = "$namespace\\$name");
        if(isset($config[$processName])){
            return $this->error($output, "Builder {$name} failed to create: Config already exists.");
        }
        /** @var AbstractBuilder $builderClass */
        $builderClass = $this->getBuilder($mode);
        if($builderClass !== null){
            file_put_contents($process, preg_replace_callback('/(];)(?!.*\1)/',
                function () use ($processName, $className, $count, $mode) {
                    return <<<EOF
    '$processName' => [
        'handler' => \\$className::class,
        'count'   => {$count},
        'mode'    => '$mode',
    ],
];
EOF;
                }, $processConfig,1));
            $this->info($output, "Config updated. $process");
            if (!is_dir($path = pathinfo($file, PATHINFO_DIRNAME))) {
                mkdir($path, 0777, true);
            }
            if(!file_exists($file)){
                file_put_contents($file, $builderClass::classContent($namespace, $name, (str_ends_with($name, 'Delayed'))));
                $this->info($output, "Builder created. $file");
            }
        }
        return $this->success($output, "Builder {$name} created successfully.");
    }
}