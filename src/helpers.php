<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use SplFileInfo;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQPublishException;

/**
 * 生产
 * @param AbstractBuilder $builder
 * @param string $body
 * @param string|null $routingKey
 * @param array|null $headers
 * @param ConnectionInterface|null $connection 传入已有连接可跳过池借还，适合 consumer 内高频调用
 * @return int|null
 */
function publish(
    AbstractBuilder $builder,
    string $body,
    ?string $routingKey = null,
    ?array $headers = null,
    ?ConnectionInterface $connection = null
): int|null {
    $config = new BuilderConfig($builder->getBuilderConfig()());
    if (
        ($config->getExchangeType() !== Constants::DELAYED and $headers['x-delay'] ?? 0) or
        ($config->getExchangeType() === Constants::DELAYED and !($headers['x-delay'] ?? 0))
    ) {
        throw new WebmanRabbitMQPublishException('Invalid publish. ');
    }
    $config->setBody($body);
    $config->setHeaders($headers ?? $config->getHeaders());
    $config->setRoutingKey($routingKey ?? $config->getRoutingKey());

    // 如果传入了已有连接，直接复用，避免 pool 借还竞态
    if ($connection !== null) {
        return $builder->publish($connection, $config);
    }

    return $builder->action(function (ConnectionInterface $connection) use ($builder, $config) {
        return $builder->publish($connection, $config);
    });
}

/**
 * 复用连接进行多次操作
 *
 * 从连接池借一个连接，执行回调后自动归还。
 * 适合高频 publish 场景，避免每次 publish() 都单独借还连接。
 *
 * ```
 * // consumer handler 中复用同一个连接
 * action(function (ConnectionInterface $connection) use ($builder, $a, $b) {
 *     publish($builder, $a, connection: $connection);
 *     publish($builder, $b, connection: $connection);
 * });
 * ```
 *
 * @param callable(ConnectionInterface): mixed $callback
 * @param string $connection
 * @return mixed
 */
function action(callable $callback, string $connection = 'default'): mixed
{
    return ConnectionsManagement::action($callback, $connection);
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

/**
 * produce a hex+ASCII dump of a binary string.
 *
 * @param string $binary Binary input string to dump.
 * @param int $bytesPerLine Number of bytes to show per line (default 16).
 * @param bool $showAscii Whether to include the ASCII column (default true).
 * @param bool $uppercase Whether hex letters are uppercase (default true).
 * @return string Formatted multi-line hex dump.
 */
function binary_dump(string $binary, int $bytesPerLine = 16, bool $showAscii = true, bool $uppercase = true): string
{
    $total = strlen($binary);
    $output = '';
    $formatHexByte = $uppercase ? '%02X' : '%02x';
    // ensure a sensible minimum
    if ($bytesPerLine < 1) {
        $bytesPerLine = 16;
    }
    for ($offset = 0; $offset < $total; $offset += $bytesPerLine) {
        // offset column (8 hex digits)
        $line = sprintf('%08X  ', $offset);
        $chunkLen = min($bytesPerLine, $total - $offset);
        $hexPart = '';
        $asciiPart = '';
        for ($i = 0; $i < $chunkLen; $i++) {
            $byte = ord($binary[$offset + $i]);
            // append hex for this byte plus a trailing space
            $hexPart .= sprintf($formatHexByte . ' ', $byte);
            // add an extra space after the 8th byte for readability (if applicable)
            if ($i === 7 && $bytesPerLine > 8) {
                $hexPart .= ' ';
            }
            // build ASCII column: printable ASCII 32..126, otherwise '.'
            if ($showAscii) {
                $asciiPart .= ($byte >= 32 && $byte <= 126) ? chr($byte) : '.';
            }
        }
        // pad hex part so the ASCII column lines up even for short final lines.
        // each byte normally contributes "XX " (3 chars). If bytesPerLine > 8 we added one extra space.
        $expectedHexLen = ($bytesPerLine * 3) + ($bytesPerLine > 8 ? 1 : 0);
        $hexPart = str_pad($hexPart, $expectedHexLen, ' ');
        if ($showAscii) {
            $line .= "$hexPart|$asciiPart|\n";
        } else {
            $line .= rtrim($hexPart) . "\n";
        }
        $output .= $line;
    }

    return $output;
}
