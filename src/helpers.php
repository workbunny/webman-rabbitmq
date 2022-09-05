<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use React\Promise\PromiseInterface;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;

/**
 * 同步生产
 * @param FastBuilder $builder
 * @param string $body
 * @param array|null $headers
 * @param bool $close
 * @return bool
 */
function sync_publish(FastBuilder $builder, string $body, ?array $headers = null, bool $close = false) : bool
{
    $message = $builder->getMessage();
    if(
        ($message->getExchangeType() !== Constants::DELAYED and $headers['x-delay'] ?? 0) or
        ($message->getExchangeType() === Constants::DELAYED and !($headers['x-delay'] ?? 0))
    ){
        throw new WebmanRabbitMQException('Invalid publish. ');
    }
    $message->setBody($body);
    if($headers !== null){
        $message->setHeaders(array_merge($message->getHeaders(), $headers));
    }
    return $builder->syncConnection()->publish($message, $close);
}

/**
 * 异步生产
 * @param FastBuilder $builder
 * @param string $body
 * @param array|null $headers
 * @param bool $close
 * @return bool|PromiseInterface
 */
function async_publish(FastBuilder $builder, string $body, ?array $headers = null, bool $close = false)
{
    $message = $builder->getMessage();
    if(
        ($message->getExchangeType() !== Constants::DELAYED and $headers['x-delay'] ?? 0) or
        ($message->getExchangeType() === Constants::DELAYED and !($headers['x-delay'] ?? 0))
    ){
        throw new WebmanRabbitMQException('Invalid publish. ');
    }
    $message->setBody($body);
    if($headers !== null){
        $message->setHeaders(array_merge($message->getHeaders(), $headers));
    }
    return $builder->connection()->publish($message, $close);
}