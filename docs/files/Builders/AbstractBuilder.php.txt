<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Bunny\Message;
use Bunny\Message as BunnyMessage;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;
use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Builders\Traits\BuilderConfigManagement;
use Workbunny\WebmanRabbitMQ\Connection\Channel;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\ConnectionsManagement;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelFulledException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQRequeueException;
use Workbunny\WebmanRabbitMQ\Traits\ConfigMethods;
use Workerman\Timer;
use Workerman\Worker;

abstract class AbstractBuilder
{
    use ConfigMethods;
    use BuilderConfigManagement;

    /**
     * @var class-string[]
     */
    protected static array $modes = [
        'queue' => QueueBuilder::class,
    ];

    /**
     * 默认连接名
     *
     * @var string|null
     */
    protected ?string $connection = 'default';

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger;

    public function __construct()
    {
        $logger = \config('plugin.workbunny.webman-rabbitmq.app.logger');
        $this->logger = is_a($logger, LoggerInterface::class, true)
            ? (is_string($logger) ? new $logger() : $logger)
            : null;

        $this->setConfig(ConnectionsManagement::config($this->connection));
        $this->setBuilderConfig(new BuilderConfig());
        ConnectionsManagement::initialize($this->connection, $this->logger);
    }

    /**
     * 获取模式
     *
     * @param string $mode
     * @return class-string|null
     */
    public static function getMode(string $mode): ?string
    {
        return static::$modes[$mode] ?? null;
    }

    /**
     * 注册模式
     *
     * @param string $mode
     * @param string $className
     * @return class-string[]
     */
    public static function registerMode(string $mode, string $className): array
    {
        if (!is_a($className, AbstractBuilder::class, true)) {
            throw new WebmanRabbitMQException("Class [{$className}] not AbstractBuilder.");
        }

        return static::$modes;
    }

    /**
     * @return string
     */
    public function getBuilderName(): string
    {
        return str_replace('\\', '.', static::class);
    }

    /**
     * 运行
     *
     * @param Closure $action = function(ConnectionInterface $connection) {}
     * @return mixed
     */
    public function action(Closure $action): mixed
    {
        return ConnectionsManagement::action($action, $this->connection);
    }

    /**
     * 发布
     *
     * @param ConnectionInterface $connection
     * @param BuilderConfig $config
     * @return bool|int
     */
    public function publish(ConnectionInterface $connection, BuilderConfig $config): bool|int
    {
        try {
            $producer = $connection->channel();
        } catch (WebmanRabbitMQChannelFulledException) {
            return ConnectionsManagement::connection(function (ConnectionInterface $connection) use ($config) {
                return $this->publish($connection, $config);
            });
        }
        $producer->exchangeDeclare(
            $producer->id(),
            $config->getExchange(),
            $config->getExchangeType(),
            $config->isPassive(),
            $config->isDurable(),
            $config->isAutoDelete(),
            $config->isInternal(),
            $config->isNowait(),
            $config->getArguments()
        );
        $producer->queueDeclare(
            $producer->id(),
            $config->getQueue(),
            $config->isPassive(),
            $config->isDurable(),
            $config->isExclusive(),
            $config->isAutoDelete(),
            $config->isNowait(),
            $config->getArguments()
        );
        $producer->queueBind(
            $producer->id(),
            $config->getQueue(),
            $config->getExchange(),
            $config->getRoutingKey(),
            $config->isNowait(),
            $config->getArguments()
        );

        return $producer->publish(
            $config->getBody(),
            $config->getHeaders(),
            $config->getExchange(),
            $config->getRoutingKey(),
            $config->isMandatory(),
            $config->isImmediate()
        );
    }

    /**
     * 消费
     *
     * @param ConnectionInterface $connection
     * @param BuilderConfig $config
     * @return void
     */
    public function consume(ConnectionInterface $connection, BuilderConfig $config): void
    {
        try {
            $consumer = $connection->channel();
        } catch (WebmanRabbitMQChannelFulledException) {
            ConnectionsManagement::connection(function (ConnectionInterface $connection) use ($config) {
                $this->consume($connection, $config);
            });

            return;
        }
        $consumer->exchangeDeclare(
            $consumer->id(),
            $config->getExchange(),
            $config->getExchangeType(),
            $config->isPassive(),
            $config->isDurable(),
            $config->isAutoDelete(),
            $config->isInternal(),
            $config->isNowait(),
            $config->getArguments()
        );
        $consumer->queueDeclare(
            $consumer->id(),
            $config->getQueue(),
            $config->isPassive(),
            $config->isDurable(),
            $config->isExclusive(),
            $config->isAutoDelete(),
            $config->isNowait(),
            $config->getArguments()
        );
        $consumer->queueBind(
            $consumer->id(),
            $config->getQueue(),
            $config->getExchange(),
            $config->getRoutingKey(),
            $config->isNowait(),
            $config->getArguments()
        );
        $consumer->consume(
            function (Message $message) use ($config, $consumer, $connection) {
                // 如果事件循环开始重启或停止时停止消费
                if (in_array($status = Worker::getStatus(), [
                    Worker::STATUS_SHUTDOWN, Worker::STATUS_RELOADING,
                ])) {
                    $this->logger->notice("Consumer stopping [worker status $status]");

                    return;
                }
                try {
                    $tag = $config->getCallback()($message, $consumer, $this);
                    if (!in_array($tag, [Constants::ACK, Constants::NACK, Constants::REQUEUE, Constants::REJECT])) {
                        $tag = Constants::ACK;
                    }
                } catch (Throwable $throwable) {
                    $tag = Constants::REQUEUE;
                    $this->logger->notice('Consume Throwable', [
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
                        $message->content,
                        $headers,
                        $message->exchange,
                        $message->routingKey,
                        $config->isMandatory(),
                        $config->isImmediate()
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
                    $id = Timer::delay(5, function (string $tag, string $call, Message $message) use (&$id, $connection) {
                        try {
                            $res = $connection->channel()->$call($message);
                            if ($res) {
                                Timer::del($id);
                            }
                        } catch (Throwable) {
                        }
                    }, [$tag, $call, $message]);
                }
            },
            $config->getQueue(),
            $config->getConsumerTag(),
            $config->isNoLocal(),
            $config->isNoAck(),
            $config->isExclusive(),
            $config->isNowait(),
            $config->getArguments()
        );
    }

    /**
     * Builder 启动时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerStart(Worker $worker): void;

    /**
     * Builder 停止时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerStop(Worker $worker): void;

    /**
     * Builder 重加载时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerReload(Worker $worker): void;

    /**
     * 消息处理
     * @param BunnyMessage $message
     * @param Channel $channel
     * @param ConnectionInterface $connection
     * @return string
     */
    abstract public function handler(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string;

    /**
     * Command 获取需要创建的类文件内容
     *
     * @param string $namespace
     * @param string $className
     * @param bool $isDelay
     * @param string $connection
     * @return string
     */
    abstract public static function classContent(string $namespace, string $className, bool $isDelay, string $connection = 'default'): string;
}
