<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections;

use Workbunny\WebmanRabbitMQ\BuilderConfig;

interface ConnectionInterface
{

    /**
     * 获取通道
     * @return mixed
     */
    public function channels(): mixed;

    /**
     * 获取消费者
     * @param bool $reuse 是否复用channel
     * @return mixed
     */
    public function getConsumer(bool $reuse): mixed;

    /**
     * 获取生产者
     * @param bool $reuse 是否复用channel
     * @return mixed
     */
    public function getProducer(bool $reuse): mixed;

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
