<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections;

use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Channels\Channel;

interface ConnectionInterface
{

    /**
     * 是否已连接
     *
     * @return bool
     */
    public function isConnected(): bool;
    /**
     * 获取通道
     * @return Channel[]
     */
    public function channels(): array;

    /**
     * 获取通道
     * @return Channel|null
     */
    public function channel(): ?Channel;

    /**
     * 发布
     *
     * @param BuilderConfig $config
     * @param bool $close
     * @return mixed
     */
    public function publish(BuilderConfig $config, bool $close = false): mixed;

    /**
     * 消费
     *
     * @param BuilderConfig $config
     * @return void
     */
    public function consume(BuilderConfig $config): void;

    /**
     * 心跳
     *
     * @return void
     */
    public function heartbeat(): void;

    /**
     * 连接/重连
     *
     * @param array $options
     * @param bool $force
     * @return void
     */
    public function reconnect(array $options = [], bool $force = true): void;

    /**
     * 关闭连接
     *
     * @param array $options
     * @return void
     */
    public function disconnect(array $options = []): void;
}
