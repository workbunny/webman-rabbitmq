<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connection;

use Bunny\ClientStateEnum;
use Bunny\Constants;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodConnectionCloseFrame;
use Bunny\Protocol\MethodConnectionCloseOkFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use Protocols\AMQP;
use Psr\Log\LoggerInterface;
use Workbunny\WebmanRabbitMQ\Connection\Traits\ConnectionMethods;
use Workbunny\WebmanRabbitMQ\Connection\Traits\InitMethods;
use Workbunny\WebmanRabbitMQ\Connection\Traits\LoggerMethods;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workbunny\WebmanRabbitMQ\Traits\ConfigMethods;
use Workerman\Coroutine;

class Connection implements ConnectionInterface
{
    use ConfigMethods;
    use LoggerMethods;
    use InitMethods;
    use ConnectionMethods;

    /** @var string|null  */
    protected ?string $id = null;

    /** @var int state */
    protected int $state = ClientStateEnum::NOT_CONNECTED;

    /** @var array<class-string<AbstractFrame>|string, array<int, array{checker: callable|null, coroutine: Coroutine\Coroutine\CoroutineInterface, timestamp: float}>> $awaits */
    protected array $awaits = [];

    /**
     * @param array $config
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->setConfig($config);
        $this->setLogger($logger);
        AMQP::$debug = boolval($this->getConfig('debug', false));
        // register mechanism handlers PLAIN
        $this->registerMechanismHandler('PLAIN', function (string $mechanism, MethodConnectionStartFrame $start) {
            // non-blocking sending
            return $this->connectionStartOk(
                $this->getClientProperties(),
                $mechanism,
                sprintf(
                    "\0%s\0%s",
                    $this->getConfig('user', 'guest'),
                    $this->getConfig('password', 'guest')
                ),
                $start->locales
            );
        });
        // register mechanism handlers AMQPLAIN
        static::registerMechanismHandler('AMQPLAIN', function (string $mechanism, MethodConnectionStartFrame $start) {
            $responseBuffer = new Buffer();
            AMQP::writer()->appendTable([
                'LOGIN'    => $this->getConfig('user', 'guest'),
                'PASSWORD' => $this->getConfig('password', 'guest'),
            ], $responseBuffer);

            $responseBuffer->discard(4);

            // non-blocking sending
            return $this->connectionStartOk(
                $this->getClientProperties(),
                $mechanism,
                $responseBuffer->read($responseBuffer->getLength()),
                $start->locales
            );
        });
        // init connection
        $this->connection();
        // init channels pool
        $this->channels();
    }

    /**
     * @return string
     */
    public function id(): string
    {
        $this->id = $this->id ?? '[workbunny/webman-rabbitmq]' . date('YmdHis') . '-' . uniqid();
        if ($this->tcpConnection) {
            $this->tcpConnection->clientId = $this->id;
        }
        return $this->id;
    }

    /**
     * @return array
     */
    public function getClientProperties(): array
    {
        return [
            'client-id' => $this->id()
            ] + $this->getConfig('client_properties', []);
    }

    /**
     * get state
     *
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * set state
     *
     * @param int $state
     * @return void
     */
    public function setState(int $state): void
    {
        $this->state = $state;
    }

    /**
     * await
     *  - string event: pub/sub mode, ONLY RUN FIRST EVENT'S CHECKER!!!
     *  - AbstractFrame: queue mode - FIFO
     *
     * @param class-string<AbstractFrame>|string $frameClassOrEvent
     * @param callable|null $checker = function(AbstractFrame|mixed $frame): bool{} DO NOT have coroutine switching in callback
     * @return AbstractFrame|mixed
     */
    public function await(string $frameClassOrEvent, null|callable $checker = null): mixed
    {
        $co = Coroutine::getCurrent();
        $this->awaits[$frameClassOrEvent][] = [
            'checker'    => $checker,
            'coroutine'  => $co,
            'timestamp'  => microtime(true),
        ];

        return $co::suspend();
    }

    /**
     * wakeup
     *
     * @param string $frameClassOrEvent
     * @param AbstractFrame|mixed $return
     * @return void
     */
    public function wakeup(string $frameClassOrEvent, mixed $return): void
    {
        if ($awaits = $this->awaits[$frameClassOrEvent] ?? []) {
            if (!is_a($frameClassOrEvent, AbstractFrame::class, true)) {
                // string event use pub/sub mode
                foreach ($awaits as $index => $await) {
                    /** @var Coroutine\Coroutine\CoroutineInterface $coroutine */
                    $coroutine = $await['coroutine'];
                    // ONLY RUN FIRST EVENT'S CHECKER!!!
                    if ($index === 0 and ($checker = $await['checker'])) {
                        if (!$checker($return)) {
                            return;
                        }
                    }
                    $coroutine->resume($return);
                }
                // remove event
                unset($this->awaits[$frameClassOrEvent]);

                return;
            }

            // AbstractFrame use queue mode - FIFO
            $await = array_shift($this->awaits[$frameClassOrEvent]);
            /** @var Coroutine\Coroutine\CoroutineInterface $coroutine */
            $coroutine = $await['coroutine'];
            if ($checker = $await['checker']) {
                if (!$checker($return)) {
                    array_unshift($this->awaits[$frameClassOrEvent], $await);

                    return;
                }
            }
            $coroutine->resume($return);
        }
    }

    /** @inheritDoc */
    public function frameSend(AbstractFrame|Buffer $frame): ?bool
    {
        return $this->tcpConnection?->send($frame);
    }

    /** @inheritDoc */
    public function masterChannel(): ?Channel
    {
        return $this->channelUsed(Constants::CONNECTION_CHANNEL);
    }

    /** @inheritDoc */
    public function connect(): void
    {
        // connect
        if (in_array($this->state, [ClientStateEnum::NOT_CONNECTED, ClientStateEnum::ERROR])) {
            $this->setState(ClientStateEnum::CONNECTING);
            // clear awaits
            $this->awaits = [];
            // exec connect
            $this->connection()->connect();
        }
        // wait for connected
        if ($this->state === ClientStateEnum::CONNECTING) {
            $this->await('connection.connected');
        }
        $this->state = ClientStateEnum::CONNECTED;
    }

    /** @inheritDoc */
    public function disconnect(array $options = []): void
    {
        // wait for disconnect
        if ($this->state === ClientStateEnum::DISCONNECTING) {
            $this->await('connection.disconnected');
        }
        // disconnect
        $replyCode = $options['replyCode'] ?? 0;
        $replyText = $options['replyText'] ?? '';
        if ($this->state === ClientStateEnum::CONNECTED) {
            $this->state = ClientStateEnum::DISCONNECTING;
            // close channels
            $this->channels()->closeConnections();
            // send connection close message
            $this->connectionClose($replyCode, $replyText, 0, 0);
            // wait for closeOk
            $this->await(MethodConnectionCloseOkFrame::class, function (MethodConnectionCloseOkFrame $frame) {
                return $frame->channel === Constants::CONNECTION_CHANNEL;
            });
            // set state
            $this->state = ClientStateEnum::NOT_CONNECTED;
            // close tcp connection
            $this->tcpConnection->destroy();
            $this->tcpConnection = null;
            // wakeup connection.disconnected, if has it
            $this->wakeup('connection.disconnected', true);
        }
    }

    /**
     * @param AbstractFrame $frame
     * @return void
     */
    public function onFrameReceived(AbstractFrame $frame): void
    {
        // connection receive
        if ($frame instanceof MethodConnectionCloseFrame) {
            $this->disconnect([
                'replyCode' => $frame->replyCode,
                'replyText' => $frame->replyText,
                'message'   => 'Connection closed by server. ',
                'code'      => Constants::STATUS_CONNECTION_FORCED,
            ]);
            throw new WebmanRabbitMQConnectException('Connection closed by server: ' . $frame->replyText, $frame->replyCode);
        }
        if ($frame instanceof HeartbeatFrame) {
            $this->lastHeartbeatRecvTime = microtime(true);

            return;
        }
        // master channel receive
        $this->masterChannel()?->onFrameReceived($frame);
    }
}
