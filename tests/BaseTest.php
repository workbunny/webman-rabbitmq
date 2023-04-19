<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use Workbunny\WebmanRabbitMQ\Commands\AbstractCommand;
use function Workbunny\WebmanRabbitMQ\config;

abstract class BaseTest extends TestCase
{
    protected function setUp(): void
    {
        AbstractBuilder::$debug = true;
        parent::setUp();
    }

    protected function exec(string $command): array
    {
        exec($command, $output, $resultCode);
        return [$output, $resultCode];
    }

    protected function passthru(string $command): array
    {
        $output = passthru($command, $resultCode);
        return [$output, $resultCode];
    }

    protected function configIsset(string $name, bool $delayed): bool
    {
        list($name, $namespace) = AbstractCommand::getFileInfo($name, $delayed);
        $config = config('plugin.workbunny.webman-rabbitmq.process', []);
        $processName = str_replace('\\', '.', "$namespace\\$name");
        return isset($config[$processName]);
    }

    protected function fileIsset(string $name, bool $delayed): bool
    {
        list(, , $file) = AbstractCommand::getFileInfo($name, $delayed);
        return file_exists($file);
    }
}