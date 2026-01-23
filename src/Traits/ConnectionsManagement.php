<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Traits;

use Webman\Context;
use Workbunny\WebmanRabbitMQ\Connections\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workerman\Coroutine\Pool;

trait ConnectionsManagement
{
    /**
     * 连接池
     *
     * @var Pool|null
     */
    private static ?Pool $connections = null;

    /**
     * @param string $connectionClass
     * @param int $max
     * @param array $config
     * @return void
     */
    public static function setConnections(string $connectionClass, int $max, array $config): void
    {
        if (!self::$connections) {
            self::$connections = new Pool($max, $config);
            self::$connections->setConnectionCreator(function () use ($connectionClass) {
                if (!is_a($connectionClass, ConnectionInterface::class, true)) {
                    throw new WebmanRabbitMQConnectException("Connection class must be a subclass of " . ConnectionInterface::class);
                }
                return new $connectionClass($this->getConfigs(), $this->logger);
            });
            self::$connections->setConnectionCloser(function (ConnectionInterface $connection) {
                $connection->disconnect();
            });
        }
    }

    /**
     * @return Pool|null
     */
    public static function getConnections(): ?Pool
    {
        return self::$connections;
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
                self::getConnections()->put($connection);
            });
        }
        return $connection;
    }
}
