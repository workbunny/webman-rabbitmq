<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Closure;
use Psr\Log\LoggerInterface;
use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Connections\ConnectionsManagement;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workbunny\WebmanRabbitMQ\Traits\BuilderConfigManagement;
use Workbunny\WebmanRabbitMQ\Traits\ConfigMethods;
use Workerman\Worker;

abstract class AbstractBuilder
{
    use ConfigMethods;

    use BuilderConfigManagement;

    /**
     * @var class-string[]
     */
    protected static array $modes = [
        'queue' => QueueBuilder::class,
    ];

    /**
     * 默认连接名
     *
     * @var string|null
     */
    protected ?string $connection = 'default';

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger;

    public function __construct()
    {
        $this->setConfig(\config("plugin.workbunny.webman-rabbitmq.rabbitmq.connections.$this->connection", []));
        if (!$this->getConfigs()) {
            throw new WebmanRabbitMQException("RabbitMQ config not found [$this->connection].");
        }
        $logger = \config('plugin.workbunny.webman-rabbitmq.app.logger');
        $this->logger = is_a($logger, LoggerInterface::class, true)
            ? (is_string($logger) ? new $logger() : $logger)
            : null;
        $this->setBuilderConfig(new BuilderConfig());
        ConnectionsManagement::initialize($this->connection, $this->logger);
    }

    /**
     * 获取模式
     *
     * @param string $mode
     * @return class-string|null
     */
    public static function getMode(string $mode): ?string
    {
        return static::$modes[$mode] ?? null;
    }

    /**
     * 注册模式
     *
     * @param string $mode
     * @param string $className
     * @return class-string[]
     */
    public static function registerMode(string $mode, string $className): array
    {
        if (!is_a($className, AbstractBuilder::class, true)) {
            throw new WebmanRabbitMQException("Class [{$className}] not AbstractBuilder.");
        }

        return static::$modes;
    }

    /**
     * @return string
     */
    public function getBuilderName(): string
    {
        return str_replace('\\', '.', static::class);
    }

    /**
     * 运行
     *
     * @param Closure $action = function(ConnectionInterface $connection) {}
     * @return mixed
     */
    public function action(Closure $action): mixed
    {
        return ConnectionsManagement::action($action, $this->connection);
    }

    /**
     * Builder 启动时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerStart(Worker $worker): void;

    /**
     * Builder 停止时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerStop(Worker $worker): void;

    /**
     * Builder 重加载时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerReload(Worker $worker): void;

    /**
     * Command 获取需要创建的类文件内容
     *
     * @param string $namespace
     * @param string $className
     * @param bool $isDelay
     * @return string
     */
    abstract public static function classContent(string $namespace, string $className, bool $isDelay): string;
}
