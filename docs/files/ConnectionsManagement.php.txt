<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Psr\Log\LoggerInterface;
use Workbunny\WebmanRabbitMQ\Connection\Connection;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workerman\Coroutine\Pool;

class ConnectionsManagement
{
    /**
     * 连接池
     *
     * @var Pool[]
     */
    private static array $pools = [];

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
        try {
            return $pool->get();
        } catch (\Throwable $e) {
            throw new WebmanRabbitMQConnectException($e->getMessage(), $e->getCode(), $e);
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
        if ($connectionObject) {
            if ($pool = self::pool($connection)) {
                try {
                    $pool->put($connectionObject);
                } catch (\Throwable) {
                }
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
            $pool->closeConnections();
            unset(self::$pools[$connection]);
        }
    }

    /**
     * 初始化
     * @param string $connection
     * @param LoggerInterface|null $logger
     * @return void
     */
    public static function initialize(string $connection = 'default', ?LoggerInterface $logger = null): void
    {
        $has = self::pool($connection);
        if ($has) {
            return;
        }
        $config = self::config($connection);
        $pool = new Pool($config['connections_pool']['max_connections'] ?? 1, $config['connections_pool'] ?? []);
        $pool->setConnectionCreator(function () use ($connection, $config, $logger) {
            $connectionClass = $config['connections'] ?? Connection::class;
            if (!is_a($connectionClass, ConnectionInterface::class, true)) {
                throw new WebmanRabbitMQConnectException('Connection class must be a subclass of ' . ConnectionInterface::class);
            }

            $connection = new $connectionClass($config['config'], $logger);
            $connection->connect();

            return $connection;
        });
        $pool->setConnectionCloser(function (ConnectionInterface $connection) {
            $connection->disconnect();
        });
        self::$pools[$connection] = $pool;
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
     * @return Pool|null
     */
    public static function pool(string$connection = 'default'): ?Pool
    {
        return self::$pools[$connection] ?? null;
    }

    /**
     * 获取所有连接池
     * @return Pool[]
     */
    public static function pools(): array
    {
        return self::$pools;
    }
}
