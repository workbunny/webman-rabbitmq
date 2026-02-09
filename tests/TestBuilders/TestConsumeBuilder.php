<?php

declare(strict_types=1);

namespace Workbunny\Tests\TestBuilders;

use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Builders\QueueBuilder;
use Workbunny\WebmanRabbitMQ\Channels\Channel;
use Workbunny\WebmanRabbitMQ\Connections\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Constants;

class TestConsumeBuilder extends QueueBuilder
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
        'name'           => 'process.workbunny.rabbitmq.TestConsumeBuilder',
        // 是否延迟
        'delayed'        => false,
        // QOS 数量
        'prefetch_count' => 1,
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
    protected ?string $exchangeName = 'process.workbunny.rabbitmq.TestConsumeBuilder';

    protected static string $logFile = '';

    /** @inheritDoc */
    public function handler(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string
    {
        file_put_contents(
            self::$logFile,
            json_encode([
                'exchange'    => $message->exchange,
                'routing_key' => $message->routingKey,
                'payload'     => $message->content,
            ], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND
        );

        return Constants::ACK;
    }

    public static function setLogFile(string $logFile)
    {
        self::$logFile = $logFile;
    }
}
