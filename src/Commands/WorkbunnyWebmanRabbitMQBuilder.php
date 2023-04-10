<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;

class WorkbunnyWebmanRabbitMQBuilder extends AbstractCommand
{
    protected static $defaultName        = 'workbunny:rabbitmq-builder';
    protected static $defaultDescription = 'Create and initialize a workbunny/webman-rabbitmq Builder. ';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'builder name');
        $this->addArgument('count', InputArgument::REQUIRED, 'builder count');
        $this->addArgument('mode', InputArgument::REQUIRED, 'builder mode : queue, rpc');
        $this->addOption('delayed', 'd', InputOption::VALUE_NONE, 'Delayed mode');
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
        $mode    = $input->getArgument('mode');
        $delayed = $input->getOption('delayed');
        list($name, $namespace, $file) = $this->getFileInfo($name, $delayed);

        if(file_exists($process = config_path() . '/plugin/workbunny/webman-rabbitmq/process.php')){
            $processConfig = file_get_contents($process);
            $config = config('plugin.workbunny.webman-rabbitmq.process', []);
            $processName = str_replace('\\', '.', $className = "$namespace\\$name");

            if(!isset($config[$processName])){
                file_put_contents($process, preg_replace_callback('/(];)(?!.*\1)/',
                    function () use ($processName, $className, $count, $mode){
                        return <<<EOF
    '$processName' => [
        'handler' => \\$className::class,
        'count'   => $count
        'mode'    => $mode
    ],
];
EOF;
                    }, $processConfig,1));
                /** @var AbstractBuilder $builderClass */
                $builderClass = $this->getBuilder($mode);
                if($builderClass !== null){
                    if (!is_dir($path = pathinfo($file, PATHINFO_DIRNAME))) {
                        mkdir($path, 0777, true);
                    }
                    if(!file_exists($file)){
                        file_put_contents($file, $builderClass::classContent($namespace, $name, (substr($name, -strlen('Delayed')) === 'Delayed')));
                    }
                    $this->success($output, "Builder {$name} created successfully.");

                }else{
                    $this->error($output, "Builder {$name} created successfully.");
                }

            }else{
                $this->error($output, "Builder {$name} failed to create: Config already exists.");
            }

        }else{
            $this->error($output, "Builder {$name} failed to create: plugin/workbunny/webman-rabbitmq/process.php does not exist.");
        }
        return self::SUCCESS;
    }
}