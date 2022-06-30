<?php
declare(strict_types=1);

namespace app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class WorkbunnyWebmanRabbitMQBuilder extends Command
{
    protected static $defaultName        = 'workbunny:rabbitmq-builder';
    protected static $defaultDescription = '创建一个workbunny/webman-rabbitmq的Builder. ';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'builder name');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $output->writeln("Make workbunny/webman-rabbitmq Builder {$name}");
        if (!($pos = strrpos($name, '/'))) {
            $name = $this->getClassName($name);
            $file = "process/workbunny/rabbitmq/{$name}.php";
            $namespace = 'process/workbunny/rabbitmq';
        } else {
            $path = substr($name, 0, $pos) . '/workbunny/rabbitmq';
            $name = $this->getClassName(substr($name, $pos + 1));
            $file = "{$path}/{$name}.php";
            $namespace = str_replace('/', '\\', $path);
        }
        $this->createBuilder($name, $namespace, $file);
        $output->writeln("<info>Builder {$name} create succeeded. </info>");

        return self::SUCCESS;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function getClassName(string $name): string
    {
        return preg_replace_callback('/:([a-zA-Z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, ucfirst($name)) . 'Builder';
    }

    /**
     * @param string $name
     * @param string $namespace
     * @param string $file
     * @return void
     */
    protected function createBuilder(string $name, string $namespace, string $file)
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $command_content = <<<EOF
<?php
declare(strict_types=1);

namespace {$namespace};

use Bunny\Channel as BunnyChannel;
use Bunny\Async\Client as BunnyClient;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\FastBuilder;

class {$name} extends FastBuilder
{
    // QOS 大小
    protected int \$prefetch_size = 0;
    // QOS 数量
    protected int \$prefetch_count = 0;
    // QOS 是否全局
    protected bool \$is_global = false;
    // 是否延迟队列
    protected bool \$delayed = false;
    // 消费回调
    public function handler(BunnyMessage \$message, BunnyChannel \$channel, BunnyClient \$client): string
    {
        // TODO 消费需要的回调逻辑
        var_dump('请重写 {$name}::handler() ');
        return Constants::ACK;
        # Constants::NACK
        # Constants::REQUEUE
    }
}
EOF;
        file_put_contents($file, $command_content);
    }

}
