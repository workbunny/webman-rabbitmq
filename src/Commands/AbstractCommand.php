<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use function Workbunny\WebmanRabbitMQ\base_path;

abstract class AbstractCommand extends Command
{
    public static string $baseProcessPath = 'process/workbunny/rabbitmq';
    public static string $baseNamespace = 'process\workbunny\rabbitmq';

    /**
     * @param string $name
     * @return string|null
     */
    protected function getBuilder(string $name): ?string
    {
        return AbstractBuilder::getBuilderClass($name);
    }

    protected function info(OutputInterface $output, string $message): void
    {
        $output->writeln("ℹ️ $message");
    }

    protected function error(OutputInterface $output, string $message): int
    {
        $output->writeln("❌ $message");
        return self::FAILURE;
    }

    protected function success(OutputInterface $output, string $message): int
    {
        $output->writeln("✅ $message");
        return self::SUCCESS;
    }

    /**
     * @param string $name
     * @param bool $isDelayed
     * @return string
     */
    public static function getClassName(string $name, bool $isDelayed): string
    {
        $class = preg_replace_callback('/:([a-zA-Z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, ucfirst($name)) . 'Builder';
        return $isDelayed ? $class . 'Delayed' : $class;
    }

    /**
     * @param string $name
     * @param bool $delayed
     * @return array = [$name, $namespace, $file]
     */
    public static function getFileInfo(string $name, bool $delayed): array
    {
        if (!($pos = strrpos($name, '/'))) {
            $name = self::getClassName($name, $delayed);
            $file = base_path() . DIRECTORY_SEPARATOR . self::$baseProcessPath . "/$name.php";
            $namespace = self::$baseNamespace;
        } else {
            $path = self::$baseProcessPath . '/' . substr($name, 0, $pos);
            $name = self::getClassName(substr($name, $pos + 1), $delayed);
            $file = base_path() . "/$path/$name.php";
            $namespace = str_replace('/', '\\', $path);
        }
        return [$name, $namespace, $file];
    }
}