<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkbunnyWebmanRabbitMQRemove extends AbstractCommand
{
    protected static $defaultName        = 'workbunny:rabbitmq-remove';
    protected static $defaultDescription = 'Remove a workbunny/webman-rabbitmq Builder. ';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'builder name');
        $this->addOption('delayed', 'd', InputOption::VALUE_NONE, 'Delayed mode');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $delayed = $input->getOption('delayed');

        list($name, $namespace, $file) = $this->getFileInfo($name, $delayed);

        $this->removeBuilder($name, $namespace, $file, $output);

        return self::SUCCESS;
    }

    /**
     * @param string $name
     * @param string $namespace
     * @param string $file
     * @param OutputInterface $output
     * @return void
     */
    protected function removeBuilder(string $name, string $namespace, string $file, OutputInterface $output)
    {
        if(file_exists($process = config_path() . '/plugin/workbunny/webman-rabbitmq/process.php')){
            $processConfig = file_get_contents($process);
            $config = config('plugin.workbunny.webman-rabbitmq.process', []);
            $processName = str_replace('\\', '.', "$namespace\\$name");

            // 清理配置文件
            if(isset($config[$processName])){
                file_put_contents($process, preg_replace_callback("/[\r\n|\n]    '$processName' => [[\s\S]*?],/",
                        function () {
                            return '';
                        }, $processConfig,1)
                );
            }
            // 清理文件
            if(file_exists($file)){
                unlink($file);
            }

            $output->writeln("<info>Builder {$name} cleared successfully. </info>");
            return;
        }
        $output->writeln("<error>Builder {$name} failed to clear: plugin/workbunny/webman-rabbitmq/process.php does not exist. </error>");
    }

}
