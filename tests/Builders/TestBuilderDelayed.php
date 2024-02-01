<?php declare(strict_types=1);

namespace Workbunny\Tests\Builders;

use Bunny\Channel as BunnyChannel;
use Bunny\Async\Client as BunnyClient;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\Builders\QueueBuilder;

class TestBuilderDelayed extends QueueBuilder
{
    /**
     * @var array = [
     *   'name'           => 'example',
     *   'delayed'        => false,
     *   'prefetch_count' => 1,
     *   'prefetch_size'  => 0,
     *   'is_global'      => false,
     *   'routing_key'    => '',
     * ]
     */
    protected array $queueConfig = [
        // 队列名称 ，默认由类名自动生成
        'name'           => 'process.workbunny.rabbitmq.TestBuilderDelayed',
        // 是否延迟
        'delayed'        => true,
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
    protected ?string $exchangeName = 'process.workbunny.rabbitmq.TestBuilderDelayed';

    /**
     * 【请勿移除该方法】
     * @param BunnyMessage $message
     * @param BunnyChannel $channel
     * @param BunnyClient $client
     * @return string
     */
    public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client): string
    {
        // TODO 请重写消费逻辑
        echo "请重写 TestBuilderDelayed::handler\n";
        return Constants::ACK;
    }
}