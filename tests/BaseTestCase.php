<?php declare(strict_types=1);

namespace Workbunny\Tests;

use PHPUnit\Framework\TestCase;
use Workbunny\WebmanRabbitMQ\Commands\AbstractCommand;

class BaseTestCase extends TestCase
{
    protected function exec(string $command): array
    {
        exec($command, $output, $resultCode);
        return [$output, $resultCode];
    }

    protected function fileIsset(string $name, bool $delayed): bool
    {
        list(, , $file) = AbstractCommand::getFileInfo($name, $delayed);
        return file_exists($file);
    }
}
