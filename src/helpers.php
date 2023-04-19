<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use React\Promise\PromiseInterface;
use SplFileInfo;
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
 * @param mixed|null $default
 * @return array|mixed|null
 */
function config(string $key = null, mixed $default = null): mixed
{
    if(AbstractBuilder::$debug) {
        Config::load(config_path());
        return Config::get($key, $default);
    }else{
        return \config($key, $default);
    }
}

/**
 * @return string
 */
function config_path(): string
{
    return AbstractBuilder::$debug ? __DIR__ . '/config' : \config_path();
}

/**
 * @return string
 */
function base_path(): string
{
    return AbstractBuilder::$debug ? dirname(__DIR__) : \base_path();
}

/**
 * @param string $path
 * @param bool $remove
 * @return bool
 */
function is_empty_dir(string $path, bool $remove = false): bool
{
    $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::FOLLOW_SYMLINKS);
    $iterator = new \RecursiveIteratorIterator($dirIterator);

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if($file->getFilename() !== '.' and $file->getFilename() !== '..'){
            if($file->isDir()){
                is_empty_dir($file->getPath());
            }else{
                return false;
            }
        }
    }
    if($remove){
        rmdir($path);
    }
    return true;
}