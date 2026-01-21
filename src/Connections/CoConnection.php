<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections;

use Bunny\AbstractClient as Client;
use Bunny\Channel;
use Bunny\Exception\BunnyException;
use Bunny\Exception\ClientException;
use Bunny\Message;
use Composer\InstalledVersions;
use React\Promise\PromiseInterface;
use Throwable;
use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use Workbunny\WebmanRabbitMQ\Connections\Traits\ClientMethods;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQPublishException;
use Workerman\Worker;
use Workerman\RabbitMQ\Client as WorkermanRabbitMQClient;
use Workbunny\WebmanRabbitMQ\Clients\CoClient;

class CoConnection implements ConnectionInterface
{
    use ClientMethods;

    /** @var array */
    protected array $_config = [];

    /**
     * connection类会同时创建两个客户端连接
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $version = InstalledVersions::getVersion('workerman/rabbitmq');
        if (!$version or version_compare($version, '2.0.0', '<')) {
            throw new WebmanRabbitMQException('Please use the version of workerman/rabbitmq greater than 2.0.0');
        }
        $this->_config = $config;
        $this->setConsumer(WorkermanRabbitMQClient::factory($config, clientClassname: CoClient::class));
        $this->setPublisher(WorkermanRabbitMQClient::factory($config, clientClassname: CoClient::class));
    }

    /**
     * @return CoClient
     * @deprecated
     */
    public function getClient(): CoClient
    {
        /** @var CoClient $client */
        $client = $this->getConsumer();
        return $client;
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

    /** @inheritdoc */
    public function publish(BuilderConfig $config, bool $close = false): bool
    {
        try {
            /** @var CoClient $client */
            $client = $this->getPublisher();
            if ($client->isConnected()) {
                $channel = $client->catchChannel(AbstractBuilder::isReuseChannel());
            } else {
                $channel = $client->connect();
                $channel = $channel->catchChannel();
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
        } catch (Throwable $throwable) {
            if ($callback = $this->getErrorCallback()) {
                \call_user_func($callback, $throwable, $this);
            }
            if ($throwable instanceof BunnyException) {
                $this->disconnect([
                    'replyCode' => $throwable->getCode(),
                    'replyText' => $throwable->getMessage(),
                ]);
            }
            return false;
        } finally {
            if ($close) {
                $this->disconnect();
            }
        }
    }

    /** @inheritdoc */
    public function consume(BuilderConfig $config): void
    {
        try {
            $client = $this->getConsumer()->connect();
            $channel = $this->_channelInit(
                $client->catchChannel(),
                $config
            );
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
                } elseif ($tag === Constants::REQUEUE) {
                    $res = $channel->ack($message);
                    if ($res) {
                        $headers = $message->headers;
                        $headers['workbunny-requeue-count'] = ($headers['workbunny-requeue-count'] ?? 0) + 1;
                        $headers['workbunny-requeue-first-time'] = $headers['workbunny-requeue-first-time'] ?? microtime(true);
                        if (!$client->publish(
                            $config->getQueue(), $message->content, $headers,
                            $config->getExchange(), $config->getRoutingKey(),
                            $config->isMandatory(), $config->isImmediate()
                        )) {
                            $c = clone $config;
                            $c->setHeaders($headers);
                            throw new WebmanRabbitMQPublishException('Consume requeue-publish failed.', 0, $c);
                        }
                    }
                } else {
                    $res = $channel->reject($message);
                }
                if (!$res) {
                    $throwable = new WebmanRabbitMQException("Consume $tag failed.");
                    if ($callback = $this->getErrorCallback()) {
                        \call_user_func($callback, $throwable, $this);
                    }
                    throw $throwable;
                }
            }, $config->getQueue(), $config->getConsumerTag(), $config->isNoLocal(), $config->isNoAck(),
                $config->isExclusive(), $config->isNowait(), $config->getArguments()
            );
            $client->run();
        } catch (Throwable $throwable) {
            if ($callback = $this->getErrorCallback()) {
                \call_user_func($callback, $throwable, $this);
            }
            $this->disconnect();
        }
        echo "Consume Start: {$config->getExchange()} | {$config->getQueue()}\n";
    }

    /** @inheritdoc */
    public function disconnect(array $options = []): void
    {
        try {
            $throwable = $options['throwable'] ?? null;
            $clientTag = $options['client'] ?? null;
            $replyCode = $throwable instanceof ClientException ? $throwable->getCode() : ($options['replyCode'] ?? 0);
            $replyText = $throwable instanceof ClientException ? $throwable->getMessage() : ($options['replyText'] ?? '');
            switch (true) {
                case $clientTag === 'consumer':
                    /** @var CoClient $client */
                    if ($client = $this->getConsumer()) {
                        if ($client->isConnected()) {
                            foreach ($client->getChannels() as $channelId => $channel) {
                                $channel->close();
                                $client->removeChannel($channelId);
                            }
                            $client->disconnect($replyCode, $replyText)->done();
                        }
                    }
                    break;
                case $clientTag === 'publisher':
                    /** @var CoClient $client */
                    if ($client = $this->getPublisher()) {
                        if ($client->isConnected()) {
                            foreach ($client->getChannels() as $channelId => $channel) {
                                $channel->close();
                                $client->removeChannel($channelId);
                            }
                            $client->disconnect($replyCode, $replyText)->done();
                        }
                    }
                    break;
                default:
                    $options['client'] = 'consumer';
                    $this->disconnect($options);
                    $options['client'] = 'publisher';
                    $this->disconnect($options);
            }
        } catch (Throwable) {
        }
    }


    /**
     * 异步发布
     * @param BuilderConfig $config
     * @param bool $close
     * @return bool
     */
    public function asyncPublish(BuilderConfig $config, bool $close = false): bool
    {
        return $this->publish($config, $close);
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
     *
     * @param PromiseInterface|Channel $channel
     * @param BuilderConfig $config
     * @return Channel
     */
    protected function _channelInit(PromiseInterface|Channel $channel, BuilderConfig $config): Channel
    {
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
        $channel->qos(
            $config->getPrefetchSize(), $config->getPrefetchCount(), $config->isGlobal()
        );
        return $channel;
    }
}