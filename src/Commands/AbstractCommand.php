<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Commands;

use Symfony\Component\Console\Command\Command;

abstract class AbstractCommand extends Command
{
    protected string $baseProcessPath = 'process/workbunny/rabbitmq/';
    protected string $baseNamespace = 'process\workbunny\rabbitmq';

    /**
     * @param string $name
     * @param bool $isDelayed
     * @return string
     */
    protected function getClassName(string $name, bool $isDelayed): string
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
    protected function getFileInfo(string $name, bool $delayed): array
    {
        if (!($pos = strrpos($name, '/'))) {
            $name = $this->getClassName($name, $delayed);
            $file = "{$this->baseProcessPath}$name.php";
            $namespace = $this->baseNamespace;
        } else {
            $path = $this->baseProcessPath . substr($name, 0, $pos);
            $name = $this->getClassName(substr($name, $pos + 1), $delayed);
            $file = "$path/$name.php";
            $namespace = str_replace('/', '\\', $path);
        }
        return [$name, $namespace, $file];
    }
}