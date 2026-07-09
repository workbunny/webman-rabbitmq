<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Psr\Log\LoggerInterface;
use Webman\Bootstrap;
use Workbunny\WebmanRabbitMQ\Connection\Connection;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workerman\Coroutine\Pool;
use Workerman\Worker;

class ConnectionsManagement implements Bootstrap
{
    /**
     * 连接池（pool 模式） / 连接（pool-less）
     *
     * @var array<string, Pool|ConnectionInterface>
     */
    private static array $pools = [];

    /**
     * @var bool[] 池模式
     */
    private static array $poolEnabled = [];

    /**
     * 是否启用连接池
     *
     * @param string $connection
     * @return bool|null
     */
    public static function isPoolEnabled(string $connection = 'default'): ?bool
    {
        return self::$poolEnabled[$connection] ?? null;
    }

    /**
     * 获取连接
     *  - 手动使用完后请调用release方法归还连接
     *
     * @param string $connection
     * @return ConnectionInterface
     */
    public static function get(string $connection = 'default'): ConnectionInterface
    {
        $pool = self::$pools[$connection] ?? null;
        if (!$pool) {
            throw new WebmanRabbitMQConnectException("Please initialize the connection [$connection] pool first");
        }
        // pool-less 模式无需归还
        if (!self::isPoolEnabled($connection)) {
            return $pool;
        }
        try {
            return $pool->get();
        } catch (\Throwable $e) {
            throw new WebmanRabbitMQConnectException(
                "[$connection] Failed to get connection <{$e->getMessage()}>",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * 归还
     *
     * @param ConnectionInterface|null $connectionObject
     * @param string $connection
     * @return void
     */
    public static function release(?ConnectionInterface $connectionObject, string $connection = 'default'): void
    {
        if (!$connectionObject) {
            return;
        }
        // pool-less 模式无需归还
        if (!self::isPoolEnabled($connection)) {
            return;
        }
        if ($pool = self::pool($connection)) {
            try {
                $pool->put($connectionObject);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @param callable $action
     * @param string $connection
     * @return mixed
     */
    public static function action(callable $action, string $connection = 'default'): mixed
    {
        return self::connection($action, $connection);
    }

    /**
     * 运行
     *
     * @param callable $action
     * @param string $connection
     * @return mixed
     */
    public static function connection(callable $action, string $connection = 'default'): mixed
    {
        try {
            $instance = self::get($connection);

            return $action($instance);
        } finally {
            self::release($instance ?? null, $connection);
        }
    }

    /**
     * @param string $connection
     * @return void
     */
    public static function destroy(string $connection = 'default'): void
    {
        if ($pool = self::pool($connection)) {
            // pool mode
            if ($pool instanceof Pool) {
                $pool->closeConnections();
            }
            // pool-less mode
            if ($pool instanceof ConnectionInterface) {
                $pool->disconnect();
            }
            unset(self::$pools[$connection]);
        }
    }

    /**
     * 重置所有连接池/专用连接（测试/热重启场景）
     * @return void
     */
    public static function reset(): void
    {
        foreach (self::$pools as $pool) {
            try {
                // pool mode
                if ($pool instanceof Pool) {
                    $pool->closeConnections();
                }
                // pool-less mode
                if ($pool instanceof ConnectionInterface) {
                    $pool->disconnect();
                }
            } catch (\Throwable) {
            }
        }
        self::$pools = [];
    }

    /**
     * 初始化
     * @param string $connection
     * @param LoggerInterface|null $logger
     * @return void
     */
    public static function initialize(string $connection = 'default', ?LoggerInterface $logger = null): void
    {
        if (self::pool($connection)) {
            return;
        }
        $config = self::config($connection);
        if (!$config) {
            throw new WebmanRabbitMQConnectException("Not found connection [$connection] config");
        }
        self::$poolEnabled[$connection] = $config['connections_pool']['enable'] ?? true;
        $creator = function () use ($connection, $config, $logger) {
            $connectionClass = $config['connection'] ?? Connection::class;
            if (!is_a($connectionClass, ConnectionInterface::class, true)) {
                throw new WebmanRabbitMQConnectException('Connection class must be a subclass of ' . ConnectionInterface::class);
            }

            $connection = new $connectionClass($config['config'], $logger);
            $connection->connect();

            return $connection;
        };

        // pool-less
        if (!self::isPoolEnabled($connection)) {
            $pool = $creator();
        } else {
            $pool = new Pool($config['connections_pool']['max_connections'] ?? 1, $config['connections_pool'] ?? []);
            $pool->setConnectionCreator($creator);
            $pool->setConnectionCloser(function (ConnectionInterface $connection) {
                $connection->disconnect();
            });
        }
        self::$pools[$connection] = $pool;
    }

    /**
     * 启动
     * @param Worker|null $worker
     * @return void
     */
    public static function start(?Worker $worker): void
    {
        $configs = \config('plugin.workbunny.webman-rabbitmq.connections', []);
        foreach ($configs as $connection => $config) {
            /** @var LoggerInterface|null $logger */
            $logger = $config['logger'] ?? null;
            try {
                self::initialize($connection, $logger);
            } catch (\Throwable $e) {
                $logger?->error("Failed to initialize RabbitMQ connection [$connection]: {$e->getMessage()}");
            }
        }
    }

    /**
     * @param string $connection
     * @return array
     */
    public static function config(string $connection): array
    {
        $config = \config("plugin.workbunny.webman-rabbitmq.connections.$connection", []);
        if (!$config) {
            throw new WebmanRabbitMQConnectException("Not found connection config for $connection");
        }

        return $config;
    }

    /**
     * 获取连接池
     * @param string $connection
     * @return Pool|ConnectionInterface|null
     */
    public static function pool(string$connection = 'default'): null|Pool|ConnectionInterface
    {
        return self::$pools[$connection] ?? null;
    }

    /**
     * 获取所有连接池
     * @return Pool[]|ConnectionInterface[]
     */
    public static function pools(): array
    {
        return self::$pools;
    }
}
