<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders\Traits;

use Workbunny\WebmanRabbitMQ\Connections\ConnectionInterface;

trait ConnectionsManagement
{
    /**
     * connection对象池
     *
     * @var ConnectionInterface[]
     */
    private static array $_connections = [];

    /**
     * 获取connections对象池
     *
     * @return ConnectionInterface[]
     */
    public static function connections(): array
    {
        return static::$_connections;
    }

    /**
     * @param string $builderName
     * @return ConnectionInterface|null
     */
    public static function connectionGet(string $builderName): ?ConnectionInterface
    {
        return static::$_connections[$builderName] ?? null;
    }

    /**
     * @param string $builderName
     * @param ConnectionInterface $connection
     * @return void
     */
    public static function connectionSet(string $builderName, ConnectionInterface $connection): void
    {
        static::$_connections[$builderName] = $connection;
    }

    /**
     * @param string $builderName
     * @return void
     */
    public static function connectionDestroy(string $builderName): void
    {
        if ($connection = static::connectionGet($builderName)) {
            $connection->disconnect();
        }
        unset(static::$_connections[$builderName]);
    }
}
