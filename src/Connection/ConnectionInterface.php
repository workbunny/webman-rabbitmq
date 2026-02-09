<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connection;

use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\ProtocolReader;
use Bunny\Protocol\ProtocolWriter;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workerman\Connection\AsyncTcpConnection;

interface ConnectionInterface
{
    /**
     * tcp connection
     *
     * @return AsyncTcpConnection|mixed
     */
    public function connection(): mixed;

    /**
     * master channel
     *
     * @return Channel
     */
    public function masterChannel(): Channel;

    /**
     * send protocol frame
     *
     * @param AbstractFrame $frame
     * @return null|bool
     */
    public function frameSend(AbstractFrame $frame): ?bool;

    /**
     * 连接/重连
     *
     * @param array{replyCode: int, replyText: string} $options
     * @return void
     * @throws WebmanRabbitMQConnectException
     */
    public function reconnect(array $options = []): void;

    /**
     * 关闭连接
     *
     * @param array{replyCode: int, replyText: string, message: string|null, code: int|null} $options
     * @return void
     * @throws WebmanRabbitMQConnectException
     */
    public function disconnect(array $options = []): void;
}
