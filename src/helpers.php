<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Bunny\Channel as BunnyChannel;
use Bunny\Client as BunnyClient;
use Bunny\Message as BunnyMessage;
use React\Promise\PromiseInterface;

/**
 * 用于临时使用的助手builder，不可用作消费者
 */
class helpers extends FastBuilder
{
    public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client): string
    {
        return Constants::NACK;
    }
}


/**
 * 同步生产
 * @param string $body
 * @param array|null $headers
 * @param bool $close
 * @return bool
 */
function sync_publish(string $body, ?array $headers = null, bool $close = false) : bool
{
    $message = helpers::instance()->getMessage();
    $message->setBody($body);
    if($headers !== null){
        $message->setHeaders($headers);
    }
    return helpers::instance()->syncConnection()->publish($message, $close);
}

/**
 * 异步生产
 * @param string $body
 * @param array|null $headers
 * @param bool $close
 * @return bool|PromiseInterface
 */
function async_publish(string $body, ?array $headers = null, bool $close = false)
{
    $message = helpers::instance()->getMessage();
    $message->setBody($body);
    if($headers !== null){
        $message->setHeaders($headers);
    }
    return helpers::instance()->connection()->publish($message, $close);
}