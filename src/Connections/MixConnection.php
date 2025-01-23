<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections;

use Bunny\AbstractClient;
use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\ChannelStateEnum;
use Bunny\Exception\BunnyException;
use Bunny\Exception\ClientException;
use Bunny\Message;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;
use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use Workbunny\WebmanRabbitMQ\Clients\AsyncClient;
use Workbunny\WebmanRabbitMQ\Clients\SyncClient;
use Workbunny\WebmanRabbitMQ\Clients\Channels\Channel as CurrentChannel;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQAsyncPublishException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workerman\Worker;

class MixConnection implements ConnectionInterface
{

    /** @var AsyncClient 异步客户端连接 */
    protected AsyncClient $_asyncClient;

    /** @var SyncClient 同步客户端连接 */
    protected SyncClient $_syncClient;

    /** @var array  */
    protected array $_config = [];

    /**
     * connection类会同时创建两个客户端连接
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->_config        = $config;
        $this->_asyncClient   = new AsyncClient($this->_config);
        $this->_syncClient    = new SyncClient($this->_config);
    }

    /**
     * @return AsyncClient
     */
    public function getAsyncClient(): AsyncClient
    {
        return $this->_asyncClient;
    }

    /**
     * @return SyncClient
     */
    public function getSyncClient(): SyncClient
    {
        return $this->_syncClient;
    }

    /**
     * @return callable|null
     */
    public function getErrorCallback(): ?callable
    {
        $errorCallback = $this->_config['error_callback'] ?? null;
        if (!is_callable($errorCallback) and !is_null($errorCallback)) {
            $errorCallback = null;
        }
        return $errorCallback;
    }

    /** @inheritdoc  */
    public function publish(BuilderConfig $config, bool $close = false): bool
    {
        try {
            if ($this->getSyncClient()->isConnected()) {
                $channel = $this->getSyncClient()->catchChannel(AbstractBuilder::isReuseChannel());
            } else {
                $channel = $this->getSyncClient()->connect()->catchChannel();
                $channel->exchangeDeclare(
                    $config->getExchange(), $config->getExchangeType(), $config->isPassive(), $config->isDurable(),
                    $config->isAutoDelete(), $config->isInternal(), $config->isNowait(), $config->getArguments()
                );
                $channel->queueDeclare(
                    $config->getQueue(), $config->isPassive(), $config->isDurable(), $config->isExclusive(),
                    $config->isAutoDelete(), $config->isNowait(), $config->getArguments()
                );
                $channel->queueBind(
                    $config->getQueue(), $config->getExchange(), $config->getRoutingKey(), $config->isNowait(),
                    $config->getArguments()
                );
            }
            return (bool)$channel->publish(
                $config->getBody(), $config->getHeaders(), $config->getExchange(), $config->getRoutingKey(),
                $config->isMandatory(), $config->isImmediate()
            );
        } catch (Throwable $throwable){
            if ($callback = $this->getErrorCallback()) {
                \call_user_func($callback, $throwable, $this);
            }
            if ($throwable instanceof BunnyException) {
                $this->close($this->getSyncClient(), $throwable);
            }
            return false;
        } finally {
            if ($close) {
                $this->close($this->getSyncClient());
            }
        }
    }

    /** @inheritdoc  */
    public function consume(BuilderConfig $config): void
    {
        // 创建连接
        $promise = $this->getAsyncClient()->connect()->then(function (AsyncClient $client){
            return $client->catchChannel();
        }, function ($reason) {
            if ($reason instanceof Throwable){
                if ($callback = $this->getErrorCallback()) {
                    \call_user_func($callback, $reason, $this);
                }
                if ($reason instanceof BunnyException) {
                    $this->close($this->getAsyncClient(), $reason);
                    throw new WebmanRabbitMQException($reason->getMessage(), $reason->getCode(), $reason);
                }
            }
            if (is_string($reason)) {
                echo "Consume rejected: $reason\n";
            }
        });
        // 通道预备
        $promise = $this->_channelInit($promise, $config);
        // 消费
        $promise->then(function (Channel $channel) use ($config) {
            echo "Consume Start: {$config->getExchange()} | {$config->getQueue()}\n";
            $channel->consume(function (Message $message, Channel $channel, Client $client) use ($config) {
                // 如果事件循环开始重启或停止时停止消费
                if (in_array($status = Worker::getStatus(), [
                    Worker::STATUS_SHUTDOWN, Worker::STATUS_RELOADING
                ])) {
                    echo "Consumer not running [worker status $status]\n";
                    return;
                }
                try {
                    $tag = \call_user_func($config->getCallback(), $message, $channel, $client);
                } catch (Throwable $throwable) {
                    $tag = Constants::REQUEUE;
                    echo "Consume Throwable: {$throwable->getMessage()}\n";
                }
                if ($tag === Constants::ACK) {
                    $res = $channel->ack($message);
                } elseif ($tag === Constants::NACK) {
                    $res = $channel->nack($message);
                } else {
                    $res = $channel->reject($message);
                }
                $res->then(function (){}, function (Throwable $throwable){
                    if ($callback = $this->getErrorCallback()) {
                        \call_user_func($callback, $throwable, $this);
                    }
                    if ($throwable instanceof BunnyException) {
                        $this->close($this->getAsyncClient(), $throwable);
                        throw new WebmanRabbitMQException($throwable->getMessage(), $throwable->getCode(), $throwable);
                    }
                })->done();
            }, $config->getQueue(), $config->getConsumerTag(), $config->isNoLocal(), $config->isNoAck(),
                $config->isExclusive(), $config->isNowait(), $config->getArguments()
            )->then(function (MethodBasicConsumeOkFrame $ok){}, function (Throwable $throwable) {
                if ($callback = $this->getErrorCallback()) {
                    \call_user_func($callback, $throwable, $this);
                }
                if ($throwable instanceof BunnyException) {
                    $this->close($this->getAsyncClient(), $throwable);
                    throw new WebmanRabbitMQException($throwable->getMessage(), $throwable->getCode(), $throwable);
                }
            })->done();
        })->done();
    }

    /** @inheritdoc  */
    public function disconnect(array $options = []): void
    {
        $throwable = $options['throwable'] ?? null;
        $client    = $options['client'] ?? null;
        $replyCode = $throwable instanceof ClientException ? $throwable->getCode() : 0;
        $replyText = $throwable instanceof ClientException ? $throwable->getMessage() : '';
        try {
            switch (true) {
                case $client instanceof AsyncClient:
                    foreach ($client->getChannels() as $channelId => $channel) {
                        if ($client->isConnected()) {
                            $client->syncChannelClose($channelId, $replyCode, $replyText, 0, 0);
                        }
                        $client->removeChannel($channelId);
                    }
                    if ($client->isConnected()) {
                        $client->syncDisconnect($replyCode, $replyText);
                    }
                    break;
                case $client instanceof SyncClient:
                    foreach ($client->getChannels() as $channelId => $channel) {
                        if ($client->isConnected()) {
                            $channel->close($replyCode, $replyText)->done();
                        }
                        $client->removeChannel($channelId);
                    }
                    if ($client->isConnected()) {
                        $client->disconnect($replyCode, $replyText)->done();
                    }
                    break;
                case $client === null:
                    if ($this->getAsyncClient()) {
                        $this->disconnect([
                            'client' => $this->getAsyncClient(),
                            'throwable' => $throwable
                        ]);
                    }
                    if ($this->getSyncClient()) {
                        $this->disconnect([
                            'client' => $this->getSyncClient(),
                            'throwable' => $throwable
                        ]);
                    }
                    break;
                default:
                    return;
            }
        } catch (Throwable) {}
    }

    /**
     * 兼容旧版
     *
     * @param AbstractClient $client
     * @param Throwable|string|null $throwable
     * @return void
     */
    public function close(AbstractClient $client, null|Throwable|string $throwable = null): void
    {
        $this->disconnect([
            'client' => $client,
            'throwable' => $throwable
        ]);
    }


    /**
     * 异步发布
     * @param BuilderConfig $config
     * @param bool $close
     * @return PromiseInterface
     */
    public function asyncPublish(BuilderConfig $config, bool $close = false) : PromiseInterface
    {
        if ($this->getAsyncClient()->isConnected()) {
            $promise = $this->getAsyncClient()->catchChannel();
        } else {
            $promise = $this->getAsyncClient()->connect()->then(function (AsyncClient $client) {
                return $client->catchChannel();
            }, function ($reason) {
                if ($reason instanceof Throwable){
                    if ($callback = $this->getErrorCallback()) {
                        \call_user_func($callback, $reason, $this);
                    }
                    if ($reason instanceof BunnyException) {
                        $this->close($this->getAsyncClient(), $reason);
                    }
                }
                if (is_string($reason)) {
                    echo "Publisher rejected: $reason\n";
                }
            });
            $promise = $this->_channelInit($promise, $config);
        }
        return $promise->then(function (Channel $channel) use ($config, $close) {
            // 如果事件循环开始重启或停止时停止停止发布
            if (in_array($status = Worker::getStatus(), [
                Worker::STATUS_SHUTDOWN, Worker::STATUS_RELOADING
            ])) {
                echo "Publisher not running [worker status $status]\n";
                $deferred = new Deferred();
                $deferred->reject(new WebmanRabbitMQAsyncPublishException(
                    "Publisher not running [worker status $status]. ",
                    -1,
                    $config
                ));
                return $deferred->promise();
            }
            // 发布
            return $channel->publish(
                $config->getBody(),$config->getHeaders(), $config->getExchange(), $config->getRoutingKey(),
                $config->isMandatory(), $config->isImmediate()
            )->then(function () use ($close, $channel) {
                if ($channel instanceof CurrentChannel) {
                    $channel->setState(ChannelStateEnum::READY);
                }
                if ($close) {
                    $this->close($this->getAsyncClient());
                }
            }, function (Throwable $throwable) {
                if ($callback = $this->getErrorCallback()) {
                    \call_user_func($callback, $throwable, $this);
                }
                if ($throwable instanceof BunnyException) {
                    $this->close($this->getAsyncClient(), $throwable);
                }
            })->done();
        }, function ($reason) {
            if ($reason instanceof Throwable){
                if ($callback = $this->getErrorCallback()) {
                    \call_user_func($callback, $reason, $this);
                }
                if ($reason instanceof BunnyException) {
                    $this->close($this->getAsyncClient(), $reason);
                }
            }
            if (is_string($reason)) {
                echo "Publisher rejected: $reason\n";
            }
        });
    }

    /**
     * @param BuilderConfig $config
     * @param bool $close
     * @return bool
     * @throws ClientException
     */
    public function syncPublish(BuilderConfig $config, bool $close = false): bool
    {
        return $this->publish($config, $close);
    }

    /**
     * 通道预备
     * @param PromiseInterface $promise
     * @param BuilderConfig $config
     * @return PromiseInterface
     */
    protected function _channelInit(PromiseInterface $promise, BuilderConfig $config): PromiseInterface
    {
        return $promise->then(function (?Channel $channel) use ($config) {
            if (!$channel) {
                throw new WebmanRabbitMQException('Could not connect to rabbitmq. [Channel is null]');
            }
            return $channel->exchangeDeclare(
                $config->getExchange(), $config->getExchangeType(), $config->isPassive(), $config->isDurable(),
                $config->isAutoDelete(), $config->isInternal(), $config->isNowait(), $config->getArguments()
            )->then(function () use ($channel) {
                return $channel;
            });
        })->then(function (Channel $channel) use ($config) {
            return $channel->queueDeclare(
                $config->getQueue(), $config->isPassive(), $config->isDurable(), $config->isExclusive(),
                $config->isAutoDelete(), $config->isNowait(), $config->getArguments()
            )->then(function () use ($channel) {
                return $channel;
            });
        })->then(function (Channel $channel) use ($config) {
            return $channel->queueBind(
                $config->getQueue(), $config->getExchange(), $config->getRoutingKey(), $config->isNowait(),
                $config->getArguments()
            )->then(function () use ($channel) {
                return $channel;
            });
        })->then(function (Channel $channel) use ($config) {
            return $channel->qos(
                $config->getPrefetchSize(), $config->getPrefetchCount(), $config->isGlobal()
            )->then(function () use ($channel) {
                return $channel;
            });
        });
    }
}