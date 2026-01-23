<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections;

use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Channels\Channel;

interface ConnectionInterface
{
    /**
     * 获取通道
     * @return Channel[]
     */
    public function channels(): array;

    /**
     * 获取消费者
     * @param bool $reuse 是否复用channel
     * @return Channel|null
     */
    public function getConsumer(bool $reuse): ?Channel;

    /**
     * 获取生产者
     * @param bool $reuse 是否复用channel
     * @return Channel|null
     */
    public function getProducer(bool $reuse): ?Channel;

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
