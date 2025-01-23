<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections;


use Throwable;
use Workbunny\WebmanRabbitMQ\BuilderConfig;

interface ConnectionInterface
{

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
     * 关闭连接
     *
     * @param array $options
     * @return void
     */
    public function disconnect(array $options = []): void;
}
