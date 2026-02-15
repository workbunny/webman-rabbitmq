<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connection\Traits;

use Bunny\ChannelStateEnum;
use Bunny\ClientStateEnum;
use Bunny\Constants;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\MethodChannelOpenOkFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use Bunny\Protocol\MethodConnectionTuneFrame;
use Protocols\AMQP;
use Webman\Context;
use Workbunny\WebmanRabbitMQ\Connection\Channel;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelFulledException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Coroutine;
use Workerman\Coroutine\Pool;
use Workerman\Timer;
use Workerman\Worker;

trait InitMethods
{
    use MechanismMethods;

    /** @var AsyncTcpConnection|null raw tcp connection */
    protected ?AsyncTcpConnection $tcpConnection = null;

    /** @var int frame max */
    protected int $frameMax = 0xFFFF;

    /** @var Pool|null channels pool */
    protected ?Pool $channels = null;

    /** @var array<int, Channel> Used channels */
    protected array $channelUsedList = [];

    /** @var array<int> Idle channel id */
    protected array $channelIds = [];

    /** @var int channel count limit */
    protected int $channelLimit = 0xFFFF;

    /** @var int server and client heartbeat interval */
    protected int $heartbeatInterval = 60;

    /** @var int heartbeat timer id */
    protected int $heartbeat = 0;

    /** @var float last heartbeat send time */
    public float $lastHeartbeatSendTime = 0.0;

    /** @var float last heartbeat receive time */
    public float $lastHeartbeatRecvTime = 0.0;

    /**
     * @return int
     */
    public function getFrameMax(): int
    {
        return $this->frameMax;
    }

    /**
     * get free channel's id
     *
     * @return int
     */
    public function getChannelId(): int
    {
        if (!$this->channelIds) {
            throw new WebmanRabbitMQChannelFulledException('No available channel.');
        }

        return array_pop($this->channelIds);
    }

    /**
     * release channel
     *
     * @param int $channelId
     * @return void
     */
    public function channelRemove(int $channelId): void
    {
        $this->channelIds[] = $channelId;
        unset($this->channelUsedList[$channelId]);
    }

    /**
     * get all used channels / get one used channel
     *
     * @param int|null $channelId
     * @return Channel|array|null
     */
    public function channelUsed(?int $channelId = null): null|Channel|array
    {
        return $channelId === null ? $this->channelUsedList : ($this->channelUsedList[$channelId] ?? null);
    }

    /**
     * get the channels pool
     *
     * @return Pool
     */
    public function channels(): Pool
    {
        if (!$this->channels) {
            $this->channels = new Pool($this->channelLimit, $this->getConfig('channels', []));
            $this->channels->setConnectionCreator(function () {
                $this->channel(true)->channelOpen($channelId = $this->getChannelId());
                // await channel.openOk
                $this->await(MethodChannelOpenOkFrame::class, function (MethodChannelOpenOkFrame $frame) use ($channelId) {
                    return intval($frame->channel) === $channelId;
                });

                // channel
                return $this->channelUsedList[$channelId] = new Channel($this, $channelId);
            });
            $this->channels->setConnectionCloser(function (Channel $channel) {
                try {
                    $channel->close();
                    $this->channelRemove($channel->id());
                } catch (\Throwable) {
                }
            });
        }

        return $this->channels;
    }

    /**
     * get ready channel
     *
     * @param bool $master
     * @return Channel
     * @throws WebmanRabbitMQChannelException
     */
    public function channel(bool $master = false): Channel
    {
        if ($master) {
            return $this->channelUsedList[Constants::CONNECTION_CHANNEL];
        }
        $channel = Context::get('workbunny.webman-rabbitmq.channel');
        if (!$channel) {
            try {
                /** @var Channel $channel */
                $channel = $this->channels()->get();
            } catch (Coroutine\Exception\PoolException|\Throwable) {
                throw new WebmanRabbitMQChannelFulledException('No available channel.');
            }
            Context::set('workbunny.webman-rabbitmq.channel', $channel);
            Coroutine::defer(function () use ($channel) {
                try {
                    // just put it back, do not care its state
                    $this->channels()->put($channel);
                } catch (\Throwable) {}
            });

            return $channel;
        }

        // check current coroutine context.channel state
        if ($channel->getState() === ChannelStateEnum::READY) {
            return $channel;
        } else {
            Context::set('workbunny.webman-rabbitmq.channel', null);
            // close it
            $this->channels()->closeConnection($channel);
            // get new channel - recursion
            $channel = $this->channel();
        }

        return $channel;
    }

    /**
     * init/get tcp client
     *
     * @return AsyncTcpConnection
     */
    public function connection(): AsyncTcpConnection
    {
        if (!$this->tcpConnection) {
            $uri = "AMQP://{$this->getConfig('host', '127.0.0.1')}:{$this->getConfig('port', 5672)}";
            $this->tcpConnection = new AsyncTcpConnection($uri, $this->getConfig('context', []));
            if ($this->getConfig('context.ssl', [])) {
                $this->tcpConnection->transport = 'ssl';
            }
            $this->tcpConnection->onConnect = function (AsyncTcpConnection $connection) {
                $this->state = ClientStateEnum::CONNECTING;
                // protocol header
                $buffer = new Buffer();
                AMQP::writer()->appendProtocolHeader($buffer);
                $this->frameSend($buffer);
                // await connection.start
                /** @var MethodConnectionStartFrame $start */
                $start = $this->await(MethodConnectionStartFrame::class);
                // check mechanism
                if (!str_contains($start->mechanisms, $mechanism = $this->getConfig('mechanism', 'PLAIN'))) {
                    throw new WebmanRabbitMQConnectException("Server does not support $mechanism mechanism (supported: {$start->mechanisms}).");
                }
                // mechanism
                if (!$handler = $this->getMechanismHandler($mechanism)) {
                    throw new WebmanRabbitMQConnectException("Client does not support $mechanism mechanism. ");
                }
                $handler($mechanism, $start);
                /** @var MethodConnectionTuneFrame $tune */
                $tune = $this->await(MethodConnectionTuneFrame::class);
                $this->channelLimit = max(min($tune->channelMax, $this->channelLimit), 1);
                // init channel ids
                foreach (range(1, $this->channelLimit) as $i) {
                    $this->channelIds[] = $i;
                }
                $this->frameMax = max(min($tune->frameMax, $this->frameMax), 1);
                // client heartbeat interval follow server
                $this->heartbeatInterval = max(min($tune->heartbeat, $this->heartbeatInterval), 1);
                $this->connectionTuneOk($this->channelLimit, $this->frameMax, $this->heartbeatInterval);
                $this->connectionOpen($this->getConfig('vhost', '/'));
                // set master channel.
                // master channel == connection,
                // master channel has channel-methods, connection has connection-methods
                $this->channelUsedList[Constants::CONNECTION_CHANNEL] = new Channel($this, Constants::CONNECTION_CHANNEL);
                ;
                $this->state = ClientStateEnum::CONNECTED;
                // set heartbeat
                $this->heartbeat = Timer::repeat($this->heartbeatInterval, function () use ($connection) {
                    if ($this->getState() === ClientStateEnum::CONNECTED) {
                        $this->connectionHeartbeat();
                        $this->lastHeartbeatSendTime = microtime(true);
                    }
                });
                // wakeup event
                $this->wakeup('connection.connected', true);
            };
            // onMessage
            $this->tcpConnection->onMessage = function (AsyncTcpConnection $connection, AbstractFrame|Buffer $data) {
                $clientId = $connection->clientId ?? 'NaN';
                if ($data instanceof Buffer) {
                    Worker::safeEcho("AMQP protocol Error [$clientId]: Invalid frame type.\n");
                    $connection->close();

                    return;
                }
                // attempt to wakeup await*
                $this->wakeup($data::class, $data);
                // connection recv
                if ($data->channel === Constants::CONNECTION_CHANNEL) {
                    $this->onFrameReceived($data);

                    return;
                }
                // channel recv
                if ($channel = ($this->channelUsed($data->channel))) {
                    $channel->onFrameReceived($data);
                }
            };
            $this->tcpConnection->onClose = function () {
                $clientId = $this->tcpConnection->clientId ?? 'NaN';
                // if not normal close, throw error
                if (!in_array($this->getState(),[ClientStateEnum::NOT_CONNECTED, ClientStateEnum::DISCONNECTING])) {
                    throw new WebmanRabbitMQConnectException("[$clientId] Connection closed.");
                }
            };
            // onError
            $this->tcpConnection->onError = function (AsyncTcpConnection $connection, $code, $msg) {
                $clientId = $connection->clientId ?? 'NaN';
                throw new WebmanRabbitMQConnectException("[$clientId]: $msg", $code);
            };
            $this->tcpConnection->onBufferDrain = function (AsyncTcpConnection $connection) {
                $clientId = $connection->clientId ?? 'NaN';
                Worker::safeEcho("AMQP protocol [$clientId]: on buffer drain.\n");
            };
            $this->tcpConnection->onBufferFull = function (AsyncTcpConnection $connection) {
                $clientId = $connection->clientId ?? 'NaN';
                Worker::safeEcho("AMQP protocol [$clientId]: on buffer full.\n");
            };
        }

        return $this->tcpConnection;
    }
}
