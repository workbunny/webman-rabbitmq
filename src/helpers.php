<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use React\Promise\PromiseInterface;
use Webman\Config;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;

/**
 * 同步生产
 * @param AbstractBuilder $builder
 * @param string $body
 * @param string|null $routingKey
 * @param array $headers
 * @param bool $close
 * @return bool
 */
function sync_publish(AbstractBuilder $builder, string $body, ?string $routingKey = null, array $headers = [], bool $close = false) : bool
{
    $config = clone $builder->getBuilderConfig();
    if(
        ($config->getExchangeType() !== Constants::DELAYED and $headers['x-delay'] ?? 0) or
        ($config->getExchangeType() === Constants::DELAYED and !($headers['x-delay'] ?? 0))
    ){
        throw new WebmanRabbitMQException('Invalid publish. ');
    }
    $config->setBody($body);
    $config->setHeaders(array_merge($config->getHeaders(), $headers));
    $config->setRoutingKey($routingKey ?? $config->getRoutingKey());

    return $builder->getConnection()->syncPublish($config, $close);
}

/**
 * 异步生产
 * @param AbstractBuilder $builder
 * @param string $body
 * @param string|null $routingKey
 * @param array $headers
 * @param bool $close
 * @return PromiseInterface
 */
function async_publish(AbstractBuilder $builder, string $body, ?string $routingKey = null, array $headers = [], bool $close = false): PromiseInterface
{
    $config = clone $builder->getBuilderConfig();
    if(
        ($config->getExchangeType() !== Constants::DELAYED and $headers['x-delay'] ?? 0) or
        ($config->getExchangeType() === Constants::DELAYED and !($headers['x-delay'] ?? 0))
    ){
        throw new WebmanRabbitMQException('Invalid publish. ');
    }
    $config->setBody($body);
    $config->setHeaders(array_merge($config->getHeaders(), $headers));
    $config->setRoutingKey($routingKey ?? $config->getRoutingKey());

    return $builder->getConnection()->asyncPublish($config, $close);
}

/**
 * @param string|null $key
 * @param $default
 * @return array|mixed|null
 */
function debug_config(string $key = null, $default = null)
{
    Config::load(__DIR__ . '/config');
    return Config::get($key, $default);
}