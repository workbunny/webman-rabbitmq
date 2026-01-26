<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections;

use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Channels\Channel;
use Workbunny\WebmanRabbitMQ\Clients\AbstractClient;

interface ConnectionInterface
{

    /**
     * 获取原始rabbitmq-client
     *
     * @return AbstractClient
     */
    public function getClient(): AbstractClient;

    /**
     * 是否已连接
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * 获取所有通道
     *
     * @return Channel[]
     */
    public function channels(): array;

    /**
     * 获取一个可用通道
     *
     * @return Channel|null
     */
    public function channel(): ?Channel;

    /**
     * 发布消息
     *
     * @param BuilderConfig $config
     * @param bool $close
     * @return mixed
     */
    public function publish(BuilderConfig $config, bool $close = false): mixed;

    /**
     * 消费消息
     *
     * @param BuilderConfig $config
     * @return void
     */
    public function consume(BuilderConfig $config): void;

    /**
     * 发送心跳
     *
     * @return void
     */
    public function heartbeat(): void;

    /**
     * 连接/重连
     *
     * @param array{replyCode: int, replyText: string} $options
     * @param bool $force 强制重连
     * @return void
     */
    public function reconnect(array $options = [], bool $force = true): void;

    /**
     * 关闭连接
     *
     * @param array{replyCode: int, replyText: string} $options
     * @return void
     */
    public function disconnect(array $options = []): void;
}
