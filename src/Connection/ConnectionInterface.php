<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connection;

use Bunny\Protocol\AbstractFrame;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelFulledException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Coroutine\Pool;

interface ConnectionInterface
{
    /**
     * get id
     *
     * @return string
     */
    public function id(): string;

    /**
     * get state
     *
     * @return int
     */
    public function getState(): int;

    /**
     * set state
     *
     * @param int $state
     * @return void
     */
    public function setState(int $state): void;

    /**
     * tcp connection
     *
     * @return AsyncTcpConnection|mixed
     */
    public function connection(): mixed;

    /**
     * get free channel
     *
     * @return Channel
     * @throws WebmanRabbitMQChannelFulledException if all channels are used
     */
    public function channel(): Channel;

    /**
     * get channels pool
     *
     * @return Pool|null
     */
    public function channels(): ?Pool;

    /**
     * master channel
     *
     * @return null|Channel
     */
    public function masterChannel(): ?Channel;

    /**
     * send protocol frame
     *
     * @param AbstractFrame $frame
     * @return null|bool
     */
    public function frameSend(AbstractFrame $frame): ?bool;

    /**
     * 连接
     *
     * @return void
     * @throws WebmanRabbitMQConnectException
     */
    public function connect(): void;

    /**
     * 关闭连接
     *
     * @param array{replyCode: int, replyText: string, message: string|null, code: int|null} $options
     * @return void
     * @throws WebmanRabbitMQConnectException
     */
    public function disconnect(array $options = []): void;
}
