<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\ConnectionsManagement;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelFulledException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workerman\Timer;
use Workerman\Worker;

use function Workbunny\WebmanRabbitMQ\publish;

/**
 * 简单队列 Builder —— 消费与生产 TCP 隔离
 *
 * 与 {@see QueueBuilder} 的区别：
 *  - `QueueBuilder` 的消费与生产默认共用同一条连接（pool-less 模式下就是同一条 TCP），
 *    消费回调阻塞时心跳随之停摆，连接被服务端关闭，生产者一并失效。
 *  - `QueueSimpleBuilder` 在 `onWorkerStart` 时从连接池「借出并长期持有」一条连接专用于消费，
 *    不归还；`handler` 内发消息时走连接池拿到的另一条连接，实现 TCP 层隔离。
 *
 * 因此连接配置必须满足（启动时校验，不满足直接抛异常）：
 *  - `connections_pool.enable = true`：必须是池模式，否则只有一条连接可用
 *  - `connections_pool.min_connections >= 2`：一条被消费占用，另一条预留给生产且不被 idle 回收
 *  - `connections_pool.max_connections >= 2`：同上，容量上限也必须容纳两条
 *
 * 使用示例：
 * ```php
 * class MyBuilder extends QueueSimpleBuilder
 * {
 *     protected ?string $connection = 'default';
 *
 *     protected array $queueConfig = [
 *         'name'           => 'my-queue',
 *         'prefetch_count' => 1,
 *     ];
 *
 *     public function handler(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string
 *     {
 *         // 走连接池中另一条连接，与消费连接隔离
 *         $this->send('done: ' . $message->content);
 *
 *         return Constants::ACK;
 *     }
 * }
 * ```
 */
abstract class QueueSimpleBuilder extends QueueBuilder
{
    /** @var ConnectionInterface|null 消费独占连接（长期持有，不归还连接池） */
    protected ?ConnectionInterface $consumerConnection = null;

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        if ($this->consumerConnection) {
            return;
        }
        try {
            // 启动前校验：消费与生产必须能各占一条连接
            $this->assertConnectionPoolConfig();

            // 借出并长期持有，消费专用；不调用 release，避免被生产者借走
            $this->consumerConnection = ConnectionsManagement::get($this->getConnectionName());
            $this->consumerConnection->connect();
            $this->consume($this->consumerConnection, $this->getBuilderConfig());
        } catch (WebmanRabbitMQChannelFulledException $exception) {
            $this->logger?->error("QueueSimpleBuilder {$worker->id} channel pool exhausted. ", [
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
            ]);
            $this->consumerConnection = null;
            ConnectionsManagement::destroy($this->connection);
            Timer::sleep($this->restartInterval ?? 5);
            $worker::stopAll();
        } catch (WebmanRabbitMQException $exception) {
            $this->logger?->info("QueueSimpleBuilder {$worker->id} exception, retry after " .
                ($this->restartInterval ?? 5) . " seconds. ", [
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile() . ':' . $exception->getLine(),
            ]);
            $this->consumerConnection = null;
            ConnectionsManagement::destroy($this->connection);
            Timer::sleep($this->restartInterval ?? 5);
            $worker::stopAll();
        }
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void
    {
        // 先释放持有的消费连接引用，再由父类销毁整个连接池
        $this->consumerConnection = null;
        parent::onWorkerStop($worker);
    }

    /**
     * 获取消费独占的连接
     *
     * @return ConnectionInterface|null
     */
    public function getConsumerConnection(): ?ConnectionInterface
    {
        return $this->consumerConnection;
    }

    /**
     * 发送消息
     *
     * 使用连接池中与消费连接不同的连接，避免消费阻塞影响生产侧心跳。
     *
     * @param string $body
     * @param string|null $routingKey
     * @param array|null $headers
     * @return int|null
     */
    public function send(string $body, ?string $routingKey = null, ?array $headers = null): int|null
    {
        return publish($this, $body, $routingKey, $headers);
    }

    /**
     * 校验连接池配置，确保消费与生产可以各占一条连接
     *
     * @return void
     * @throws WebmanRabbitMQException
     */
    protected function assertConnectionPoolConfig(): void
    {
        $name = $this->getConnectionName();

        if (!ConnectionsManagement::isPoolEnabled($name)) {
            throw new WebmanRabbitMQException(
                "QueueSimpleBuilder requires connection [$name] to enable the connection pool " .
                "(connections_pool.enable = true). The consumer holds one connection and never returns it, " .
                "publishing needs another one, so pool-less mode cannot satisfy this."
            );
        }

        $poolConfig = ConnectionsManagement::config($name)['connections_pool'] ?? [];

        $minConnections = (int)($poolConfig['min_connections'] ?? 1);
        if ($minConnections < 2) {
            throw new WebmanRabbitMQException(
                "QueueSimpleBuilder requires connections_pool.min_connections >= 2 for connection [$name], " .
                "current: {$minConnections}. The consumer holds one connection permanently, " .
                "the reserved one must not be reclaimed by idle_timeout."
            );
        }

        $maxConnections = (int)($poolConfig['max_connections'] ?? 1);
        if ($maxConnections < 2) {
            throw new WebmanRabbitMQException(
                "QueueSimpleBuilder requires connections_pool.max_connections >= 2 for connection [$name], " .
                "current: {$maxConnections}."
            );
        }
    }

    /** @inheritDoc */
    public static function classContent(string $namespace, string $className, bool $isDelay, string $connection = 'default'): string
    {
        $isDelay = $isDelay ? 'true' : 'false';
        $name = str_replace('\\', '.', "$namespace.$className");

        return <<<doc
<?php declare(strict_types=1);

namespace $namespace;

use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Connection\Channel;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\Builders\QueueSimpleBuilder;

/**
 * 简单队列 Builder —— 消费与生产 TCP 隔离
 *
 * ⚠️ 要求连接 [$connection] 开启连接池：connections_pool.enable = true 且
 *    min_connections >= 2、max_connections >= 2（消费占一条，生产用另一条）
 */
class $className extends QueueSimpleBuilder
{
    /** @inheritdoc  */
    protected ?string \$connection = '$connection';

    /**
     * 队列配置
     *
     * @var array{
     *     name: string,
     *     delayed: bool,
     *     prefetch_count: int,
     *     prefetch_size: int,
     *     is_global: bool,
     *     routing_key: string,
     *     is_requeue: bool,
     * } = [
     *            'name'           => 'example',
     *            'delayed'        => false,
     *            'prefetch_count' => 1,
     *            'prefetch_size'  => 0,
     *            'is_global'      => false,
     *            'routing_key'    => '',
     *            'is_requeue'     => true,
     *     ]
     */
    protected array \$queueConfig = [
        // 队列名称 ，默认由类名自动生成
        'name'           => '$name',
        // 是否延迟
        'delayed'        => $isDelay,
        // QOS 数量
        'prefetch_count' => 0,
        // QOS size
        'prefetch_size'  => 0,
        // QOS 全局
        'is_global'      => false,
        // 路由键
        'routing_key'    => '',
    ];

    /** @var string 交换机类型 */
    protected string \$exchangeType = Constants::DIRECT;

    /** @var string|null 交换机名称,默认由类名自动生成 */
    protected ?string \$exchangeName = '$name';

    /** @inheritDoc */
    public function handler(BunnyMessage \$message, Channel \$channel, ConnectionInterface \$connection): string
    {
        // TODO 请重写消费逻辑
        // 需要发送消息时使用 \$this->send('body')，
        // 它会走连接池中与消费连接不同的另一条连接，避免阻塞消费心跳
        echo "请重写 $className::handler\\n";
        return Constants::ACK;
    }
}
doc;
    }
}
