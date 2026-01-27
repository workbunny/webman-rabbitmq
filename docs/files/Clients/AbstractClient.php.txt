<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Clients;

use Bunny\ClientStateEnum;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use Psr\Log\LoggerInterface;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workbunny\WebmanRabbitMQ\Traits\ClientMethods;
use Workbunny\WebmanRabbitMQ\Traits\ConfigMethods;
use Workerman\RabbitMQ\Traits\LoggerMethods;
use Workerman\RabbitMQ\Traits\MechanismMethods;
use Workerman\Timer;
use Workerman\Worker;

abstract class AbstractClient extends \Bunny\Client
{
    use ClientMethods;
    use ConfigMethods;
    use LoggerMethods;
    use MechanismMethods;

    /**
     * 心跳定时器
     *
     * @var int|null
     */
    protected ?int $heartbeat = null;

    /**
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $options = [], ?LoggerInterface $logger = null)
    {
        $this->setConfig($options);
        // 日志
        $this->setLogger($logger);
        // 注册认证机制
        static::registerMechanismHandler('PLAIN', function (string $mechanism, MethodConnectionStartFrame $start) {
            return $this->connectionStartOk([], $mechanism, sprintf("\0%s\0%s", $this->options['user'], $this->options['password']), 'en_US');
        });
        static::registerMechanismHandler('AMQPLAIN', function (string $mechanism, MethodConnectionStartFrame $start) {
            $responseBuffer = new Buffer();
            $this->writer->appendTable([
                'LOGIN'    => $this->options['user'],
                'PASSWORD' => $this->options['password'],
            ], $responseBuffer);

            $responseBuffer->discard(4);

            return $this->connectionStartOk([], $mechanism, $responseBuffer->read($responseBuffer->getLength()), 'en_US');
        });
        parent::__construct($options);
    }

    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->disconnect();
        }
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        if ($this->isRunning()) {
            $this->writer->appendFrame(new HeartbeatFrame(), $this->writeBuffer);
            $this->flushWriteBuffer();
            if (is_callable(
                $this->options['heartbeat_callback'] ?? null
            )) {
                $this->options['heartbeat_callback']($this);
            }
        }
    }

    /** @inheritDoc */
    public function disconnect($replyCode = 0, $replyText = ''): void
    {
        if ($this->isConnected()) {
            $this->connectionClose($replyCode, $replyText, 0, 0);
            $this->state = ClientStateEnum::NOT_CONNECTED;
        }
        if ($this->heartbeat) {
            Timer::del($this->heartbeat);
            $this->heartbeat = null;
        }
        if ($this->getStream()) {
            Worker::$globalEvent->offReadable($this->getStream());
            $this->closeStream();
        }
        $this->init();
    }

    /** @inheritDoc */
    public function connect(): AbstractClient
    {
        $res = parent::connect();
        // 可读事件
        Worker::$globalEvent->onReadable($this->getStream(), [$this, 'onDataAvailable']);
        $this->heartbeat = Timer::add($this->options['heartbeat'] ?? 50, [$this, 'heartbeat']);
        $this->running = true;

        return $res;
    }

    /**
     * @return void
     */
    public function onDataAvailable(): void
    {
        $this->read();
        while (($frame = $this->reader->consumeFrame($this->readBuffer)) !== null) {
            if ($frame->channel === 0) {
                $this->onFrameReceived($frame);
            } else {
                if (!isset($this->channels[$frame->channel])) {
                    throw new WebmanRabbitMQChannelException(
                        "Received frame #{$frame->type} on closed channel #{$frame->channel}."
                    );
                }

                $this->channels[$frame->channel]->onFrameReceived($frame);
            }
            // 出让协程避免阻塞
            Timer::sleep(rand(10, 99) / 1000);
        }
    }

    /**
     * @param MethodConnectionStartFrame $start
     * @return bool
     */
    protected function authResponse(MethodConnectionStartFrame $start): bool
    {
        $mechanism = $this->options['mechanism'] ?? 'AMQPLAIN';
        if (!str_contains($start->mechanisms, $mechanism)) {
            throw new WebmanRabbitMQConnectException("Server does not support $mechanism mechanism (supported: {$start->mechanisms}).");
        }
        // 认证机制
        if ($handler = static::getMechanismHandler($mechanism)) {
            return $handler($mechanism, $start);
        }
        throw new WebmanRabbitMQConnectException("Client does not support $mechanism mechanism. ");
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}
