<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use React\Promise\PromiseInterface;

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
    $message->setBody($body);
    if($headers !== null){
        $message->setHeaders($headers);
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
    $message->setBody($body);
    if($headers !== null){
        $message->setHeaders($headers);
    }
    return $builder->connection()->publish($message, $close);
}