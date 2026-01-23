<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use SplFileInfo;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQPublishException;

/**
 * 生产
 * @param AbstractBuilder $builder
 * @param string $body
 * @param string|null $routingKey
 * @param array|null $headers
 * @param bool $close 执行完是否关闭channel
 * @return bool
 */
function publish(AbstractBuilder $builder, string $body, ?string $routingKey = null, ?array $headers = null, bool $close = false): bool
{
    $config = $builder->getBuilderConfig();
    if (
        ($config->getExchangeType() !== Constants::DELAYED and $headers['x-delay'] ?? 0) or
        ($config->getExchangeType() === Constants::DELAYED and !($headers['x-delay'] ?? 0))
    ) {
        throw new WebmanRabbitMQPublishException('Invalid publish. ');
    }
    $config->setBody($body);
    $config->setHeaders($headers ?? $config->getHeaders());
    $config->setRoutingKey($routingKey ?? $config->getRoutingKey());

    return $builder->connection()->publish($config, $close);
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
        if ($file->getFilename() !== '.' and $file->getFilename() !== '..') {
            if ($file->isDir()) {
                is_empty_dir($file->getPath());
            } else {
                return false;
            }
        }
    }
    if ($remove) {
        rmdir($path);
    }
    return true;
}