<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections;

use Bunny\Message;
use Psr\Log\LoggerInterface;
use Throwable;
use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Channels\Channel;
use Workbunny\WebmanRabbitMQ\Clients\Client;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQRequeueException;
use Workbunny\WebmanRabbitMQ\Traits\ConfigMethods;
use Workerman\Timer;
use Workerman\Worker;

class Connection implements ConnectionInterface
{

    use ConfigMethods;

    /**
     * @var Client
     */
    protected Client $client;

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var bool
     */
    protected bool $isConnected = false;

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;

    /**
     * 初始化连接
     * @param array $config
     * @param string $name
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $config, string $name, ?LoggerInterface $logger = null)
    {
        $this->name = $name;
        $this->setConfig($config);
        $this->client = new Client($this->getConfig('config', []), $logger);
        $this->logger = $logger;
        $this->reconnect(force: true);
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->isConnected and $this->client->isConnected();
    }

    /** @inheritDoc */
    public function heartbeat(): void
    {
        if ($this->isConnected()) {
            $this->client->heartbeat();
        }
    }

    /**
     * @inheritDoc
     * @param array $options
     * @param bool $force
     * @return void
     * @throws WebmanRabbitMQConnectException
     */
    public function reconnect(array $options = [], bool $force = false): void
    {
        if ($force and $this->isConnected()) {
            $this->disconnect($options);
        }
        if (!$this->isConnected()) {
            try {
                $this->client->connect();
                $this->isConnected = true;
            } catch (Throwable $throwable) {
                throw new WebmanRabbitMQConnectException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
        }
    }

    /** @inheritDoc */
    public function disconnect(array $options = []): void
    {
        try {
            if ($this->isConnected()) {
                $channels = $this->client->getChannels();
                foreach ($channels as $channelId => $channel) {
                    try {
                        $channel->close();
                    } catch (Throwable) {
                    } finally {
                        $this->client->removeChannel($channelId);
                    }
                }
                $this->client->disconnect($options['replyCode'] ?? 0, $options['replyText'] ?? '');
                $this->isConnected = false;
            }
        } catch (Throwable) {}
    }

    /** @inheritDoc */
    public function channels(): array
    {
        return $this->isConnected() ? $this->client->getChannels() : [];
    }

    /** @inheritDoc */
    public function channel(): ?Channel
    {
        if (!$this->isConnected()) {
            $this->reconnect();
        }
        return $this->client->channel();
    }

    /** @inheritDoc */
    public function publish(BuilderConfig $config, bool $close = false): bool|int
    {
        try {
            $producer = $this->channel();
        } catch (WebmanRabbitMQChannelException $e) {
            // 当前连接通道耗尽时，尝试使用新连接
            if ($e->getCode() === -999999999) {
                return ConnectionsManagement::connection(function (ConnectionInterface $connection) use ($config, $close) {
                    $connection->publish($config, $close);
                });
            }
            throw $e;
        }

        try {
            $producer->exchangeDeclare(
                $config->getExchange(), $config->getExchangeType(), $config->isPassive(), $config->isDurable(),
                $config->isAutoDelete(), $config->isInternal(), $config->isNowait(), $config->getArguments()
            );
            $producer->queueDeclare(
                $config->getQueue(), $config->isPassive(), $config->isDurable(), $config->isExclusive(),
                $config->isAutoDelete(), $config->isNowait(), $config->getArguments()
            );
            $producer->queueBind(
                $config->getQueue(), $config->getExchange(), $config->getRoutingKey(), $config->isNowait(),
                $config->getArguments()
            );
            return $producer->publish(
                $config->getBody(), $config->getHeaders(), $config->getExchange(), $config->getRoutingKey(),
                $config->isMandatory(), $config->isImmediate()
            );
        } finally {
            if ($close) {
                $producer->close();
            }
        }
    }

    /** @inheritdoc */
    public function consume(BuilderConfig $config): void
    {
        try {
            $consumer = $this->channel();
        } catch (WebmanRabbitMQChannelException $e) {
            // 当前连接通道耗尽时，尝试使用新连接
            if ($e->getCode() === -999999999) {
                ConnectionsManagement::connection(function (ConnectionInterface $connection) use ($config) {
                    $connection->consume($config);
                });
                return;
            }
            throw $e;
        }
        $consumer->exchangeDeclare(
            $config->getExchange(), $config->getExchangeType(), $config->isPassive(), $config->isDurable(),
            $config->isAutoDelete(), $config->isInternal(), $config->isNowait(), $config->getArguments()
        );
        $consumer->queueDeclare(
            $config->getQueue(), $config->isPassive(), $config->isDurable(), $config->isExclusive(),
            $config->isAutoDelete(), $config->isNowait(), $config->getArguments()
        );
        $consumer->queueBind(
            $config->getQueue(), $config->getExchange(), $config->getRoutingKey(), $config->isNowait(),
            $config->getArguments()
        );
        $consumer->consume(function (Message $message) use ($config, $consumer) {
            // 如果事件循环开始重启或停止时停止消费
            if (in_array($status = Worker::getStatus(), [
                Worker::STATUS_SHUTDOWN, Worker::STATUS_RELOADING
            ])) {
                $this->logger->notice("Consumer stopping [worker status $status]");
                return;
            }
            try {
                $tag = \call_user_func($config->getCallback(), $message, $consumer, $this);
                if (!in_array($tag, [Constants::ACK, Constants::NACK, Constants::REQUEUE, Constants::REJECT])) {
                    $tag = Constants::ACK;
                }
            } catch (Throwable $throwable) {
                $tag = Constants::REQUEUE;
                $this->logger->notice("Consume Throwable", [
                    'message' => $throwable->getMessage(),
                    'code'    => $throwable->getCode(),
                    'file'    => $throwable->getFile() . ':' . $throwable->getLine(),
                ]);
            }
            // requeue原则保证重试，不保证可能存在多次消费，因为原数据可能ack失败
            if ($tag === Constants::REQUEUE) {
                $headers = $message->headers;
                $headers['workbunny-requeue-count'] = ($headers['workbunny-requeue-count'] ?? 0) + 1;
                $headers['workbunny-requeue-first-time'] = $headers['workbunny-requeue-first-time'] ?? microtime(true);
                if (!$consumer->publish(
                    $message->content, $headers, $message->exchange, $message->routingKey,
                    $config->isMandatory(), $config->isImmediate()
                )) {
                    $c = clone $config;
                    $c->setHeaders($headers);
                    throw new WebmanRabbitMQRequeueException('Consume requeue-publish failed.', 0, $c);
                }
            }
            $call = $tag === Constants::REQUEUE ? Constants::ACK : $tag;
            $res = $consumer->$call($message);
            if (!$res) {
                $this->logger->notice("Consume $tag failed [timer retrying].");
                // ACK失败则定时器重试，直到成功
                $id = Timer::delay(5, function (string $tag, string $call, Message $message) use (&$id) {
                    try {
                        $res = $this->channel()->$call($message);
                        if ($res) {
                            Timer::del($id);
                        }
                    } catch (Throwable) {}
                }, [$tag, $call, $message]);
            }
        }, $config->getQueue(), $config->getConsumerTag(), $config->isNoLocal(), $config->isNoAck(),
            $config->isExclusive(), $config->isNowait(), $config->getArguments()
        );
    }
}