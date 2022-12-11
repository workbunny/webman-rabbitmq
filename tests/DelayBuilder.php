<?php
declare(strict_types=1);

namespace Tests;

use Bunny\Async\Client as BunnyClient;
use Bunny\Channel as BunnyChannel;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\FastBuilder;

class DelayBuilder extends FastBuilder
{
    protected int $prefetch_size = 1;
    protected int $prefetch_count = 0;
    protected bool $is_global = false;
    protected bool $delayed = true;

    public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client): string
    {
        var_dump($message->content);
        return Constants::ACK;
        # Constants::NACK
        # Constants::REQUEUE
    }
}