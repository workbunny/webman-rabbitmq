<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Bunny\AbstractClient;
use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Exception\ClientException;
use Bunny\Message;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;
use Workbunny\WebmanRabbitMQ\Clients\AsyncClient;
use Workbunny\WebmanRabbitMQ\Clients\SyncClient;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQAsyncPublishException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workerman\Worker;

class Connection
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
        if(!is_callable($errorCallback) and !is_null($errorCallback)){
            $errorCallback = null;
        }
        return $errorCallback;
    }

    /**
     * 兼容旧版
     *
     * @param AbstractClient $client
     * @param Throwable|null $throwable
     * @return void
     * @deprecated
     */
    public function close(AbstractClient $client, ?Throwable $throwable = null): void
    {
        $this->disconnect($client, $throwable);
    }

    /**
     * 关闭连接
     *
     * @param null|AbstractClient $client null: 关闭所有连接, 其他:关闭指定连接
     * @param Throwable|null $throwable
     * @return void
     */
    public function disconnect(?AbstractClient $client, ?Throwable $throwable = null): void
    {
        $replyCode = $throwable instanceof ClientException ? $throwable->getCode() : 0;
        $replyText = $throwable instanceof ClientException ? $throwable->getMessage() : '';
        try {
            if (!$client) {
                $this->disconnect($this->getAsyncClient(), $throwable);
                $this->disconnect($this->getSyncClient(), $throwable);
            }
            if ($client instanceof AsyncClient) {
                $channels = $client->getChannels();
                foreach ($channels as $id => $channel) {
                    if ($client->isConnected()) {
                        $channel->close($replyCode, $replyText);
                    }
                    $client->removeChannel($id);
                }
                $client->syncDisconnect($replyCode, $replyText);
            }
            if ($client instanceof SyncClient) {
                $channels = $client->getChannels();
                foreach ($channels as $id => $channel) {
                    if ($client->isConnected()) {
                        $channel->close($replyCode, $replyText);
                    }
                    $client->removeChannel($id);
                }
                $client->disconnect($replyCode, $replyText);
            }
        } catch (Throwable) {}
    }

    /**
     * @param BuilderConfig $config
     * @return void
     */
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
                $this->disconnect($this->getAsyncClient(), $reason);
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
                        $this->disconnect($this->getAsyncClient(), $throwable);
                    })->done();
                }, $config->getQueue(), $config->getConsumerTag(), $config->isNoLocal(), $config->isNoAck(),
                $config->isExclusive(), $config->isNowait(), $config->getArguments()
            )->then(function (MethodBasicConsumeOkFrame $ok){}, function (Throwable $throwable) {
                if ($callback = $this->getErrorCallback()) {
                    \call_user_func($callback, $throwable, $this);
                }
                $this->disconnect($this->getAsyncClient(), $throwable);
            })->done();
        })->done();
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
                    $this->disconnect($this->getAsyncClient(), $reason);
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
            )->then(function () use ($close) {
                if ($close) {
                    $this->disconnect($this->getAsyncClient());
                }
            }, function (Throwable $throwable) {
                if ($callback = $this->getErrorCallback()) {
                    \call_user_func($callback, $throwable, $this);
                }
                $this->disconnect($this->getAsyncClient(), $throwable);
            })->done();
        }, function ($reason) {
            if ($reason instanceof Throwable){
                if ($callback = $this->getErrorCallback()) {
                    \call_user_func($callback, $reason, $this);
                }
                $this->disconnect($this->getAsyncClient(), $reason);
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
     */
    public function syncPublish(BuilderConfig $config, bool $close = false): bool
    {
        try {
            if ($this->getSyncClient()->isConnected()) {
                $channel = $this->getSyncClient()->catchChannel();
            } else {
                try {
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
                } catch (Throwable $throwable) {
                    if ($throwable instanceof ClientException) {
                        throw $throwable;
                    }
                    if ($callback = $this->getErrorCallback()) {
                        \call_user_func($callback, $throwable, $this);
                    }
                    $this->disconnect($this->getSyncClient(), $throwable);
                    return false;
                }
            }
        } catch (Throwable $throwable){
            if ($callback = $this->getErrorCallback()) {
                \call_user_func($callback, $throwable, $this);
            }
            $this->disconnect($this->getSyncClient(), $throwable);
            return false;
        }
        $res = (bool)$channel->publish(
            $config->getBody(), $config->getHeaders(), $config->getExchange(), $config->getRoutingKey(),
            $config->isMandatory(), $config->isImmediate()
        );
        if ($close) {
            $this->disconnect($this->getSyncClient());
        }
        return $res;
    }

    /**
     * 通道预备
     * @param PromiseInterface $promise
     * @param BuilderConfig $config
     * @return PromiseInterface
     */
    protected function _channelInit(PromiseInterface $promise, BuilderConfig $config): PromiseInterface
    {
        return $promise->then(function (Channel $channel) use ($config) {
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