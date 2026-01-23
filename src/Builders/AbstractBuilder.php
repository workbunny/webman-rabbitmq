<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Psr\Log\LoggerInterface;
use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Connections\Connection;
use Workbunny\WebmanRabbitMQ\Connections\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workbunny\WebmanRabbitMQ\Traits\BuilderConfigManagement;
use Workbunny\WebmanRabbitMQ\Traits\ConfigMethods;
use Workerman\Coroutine\Pool;
use Workerman\Worker;
use Webman\Context;

abstract class AbstractBuilder
{

    use ConfigMethods,
        BuilderConfigManagement;

    /**
     * 连接池
     *
     * @var Pool|null
     */
    protected static ?Pool $connections = null;

    /**
     * @var class-string[]
     */
    protected static array $modes = [
        'queue' => QueueBuilder::class
    ];

    /**
     * 默认连接名
     *
     * @var string|null
     */
    protected ?string $connection = 'rabbitmq';

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
        if (!self::$connections) {
            self::$connections = new Pool($this->getConfig('pool.max_connections', 1), $this->getConfig('pool', []));
            self::$connections->setConnectionCreator(function () {
                $connection = $this->getConfig('pool.connection_class', Connection::class);
                if (!is_a($connection, ConnectionInterface::class, true)) {
                    throw new WebmanRabbitMQConnectException("RabbitMQ connection class [{$connection}] not found.");
                }
                return new $connection($this->getConfigs(), $this->logger);
            });
            self::$connections->setConnectionCloser(function (ConnectionInterface $connection) {
                $connection->disconnect();
            });
        }
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
     * @return Pool|null
     */
    public static function getConnections(): ?Pool
    {
        return self::$connections;
    }

    /**
     * @return string
     */
    public function getBuilderName(): string
    {
        return str_replace('\\', '.', static::class);
    }

    /**
     * @return ConnectionInterface
     */
    public function connection(): ConnectionInterface
    {
        $connection = Context::get('workbunny.webman-rabbitmq.connection');
        if (!$connection) {
            try {
                $connection = self::$connections->get();
            } catch (\Throwable $e) {
                throw new WebmanRabbitMQConnectException($e->getMessage(), $e->getCode(), $e);
            }
            Context::set('workbunny.webman-rabbitmq.connection', $connection);
            Context::onDestroy(function () use ($connection) {
                self::$connections->put($connection);
            });
        }
        return $connection;
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