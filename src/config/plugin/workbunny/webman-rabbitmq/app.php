<?php declare(strict_types=1);

return [
    'enable' => true,
    // 日志 LoggerInterface | LoggerInterface::class
    'logger'   => null,
    // 连接 ConnectionInterface | ConnectionInterface::class
    'connection' => \Workbunny\WebmanRabbitMQ\Connections\Connection::class
];