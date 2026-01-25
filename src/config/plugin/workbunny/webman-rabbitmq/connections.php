<?php declare(strict_types=1);

use Workbunny\WebmanRabbitMQ\Clients\AbstractClient;
use Workbunny\WebmanRabbitMQ\Connections\Connection;

return [
    'default' => [
        'connection'       => Connection::class,
        // 连接池，用于支撑影子模式
        'connections_pool' => [
            'min_connections'       => 1,
            'max_connections'       => 10,
            'idle_timeout'          => 60,
            'wait_timeout'          => 10
        ],
        'config' => [
            'host'               => 'rabbitmq',
            'vhost'              => '/',
            'port'               => 5672,
            'username'           => 'guest',
            'password'           => 'guest',
            'mechanism'          => 'AMQPLAIN',
            'timeout'            => 10,
            // 重启间隔
            'restart_interval'   => 5,
            // 心跳间隔
            'heartbeat'          => 50,
            // 通道池
            'channels_pool'      => [
                'idle_timeout'     => 60,
                'wait_timeout'     => 10
            ],
            'client_properties' => [
                'name'     => 'workbunny/webman-rabbitmq',
                'version'  => \Composer\InstalledVersions::getVersion('workbunny/webman-rabbitmq')
            ],
            // 心跳回调 callable
            'heartbeat_callback' => null,
        ]
    ]
];