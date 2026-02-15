<?php

declare(strict_types=1);

namespace Workbunny\Tests\TestBuilders;

use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Builders\QueueBuilder;
use Workbunny\WebmanRabbitMQ\Connection\Channel;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Constants;

class TestPublishBuilder extends QueueBuilder
{
    /** @inheritdoc  */
    protected ?string $connection = 'default';

    /**
     * @var array = [
     *            'name'           => 'example',
     *            'delayed'        => false,
     *            'prefetch_count' => 1,
     *            'prefetch_size'  => 0,
     *            'is_global'      => false,
     *            'routing_key'    => '',
     *            ]
     */
    protected array $queueConfig = [
        // 队列名称 ，默认由类名自动生成
        'name'           => 'process.workbunny.rabbitmq.TestPublishBuilder',
        // 是否延迟
        'delayed'        => false,
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
    protected string $exchangeType = Constants::DIRECT;

    /** @var string|null 交换机名称,默认由类名自动生成 */
    protected ?string $exchangeName = 'process.workbunny.rabbitmq.TestPublishBuilder';

    /** @inheritDoc */
    public function handler(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string
    {
        return Constants::ACK;
    }
}
