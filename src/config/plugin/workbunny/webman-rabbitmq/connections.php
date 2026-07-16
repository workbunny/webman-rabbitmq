<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Workbunny\WebmanRabbitMQ\Connection\Connection;

return [
    'default' => [
        'connection'       => Connection::class,
        // 连接池，用于支撑影子模式
        //  - enable: true 使用连接池，false 使用专用长连接（consumer/publish 共用，无借还竞态）
        //  - ⚠️ 影子模式（channel 池耗尽时换连接重试）的递归深度由 max_connections 控制，
        //     不要将 max_connections 设得过大，避免递归调用栈过深
        'connections_pool' => [
            'enable'                => true,
            'min_connections'       => 1,
            'max_connections'       => 20,
            'idle_timeout'          => 60,
            'wait_timeout'          => 10,
        ],
        'logger'                 => null,
        'config'                 => [
            'debug'              => false,
            'host'               => '127.0.0.1',
            'vhost'              => '/',
            'port'               => 5672,
            'username'           => 'guest',
            'password'           => 'guest',
            'mechanism'          => 'AMQPLAIN',
            'timeout'            => 10,
            // 重启间隔
            'restart_interval'   => 5,
            // 通道池，max_connections 限制单连接最大通道数
            'channels_pool'      => [
                'max_connections'  => null,
                'idle_timeout'     => 60,
                'wait_timeout'     => 10,
            ],
            'client_properties' => [
                'name'     => 'workbunny/webman-rabbitmq',
                'version'  => InstalledVersions::getVersion('workbunny/webman-rabbitmq'),
            ],
            // 心跳回调 callable
            'heartbeat_callback' => function () {
            },

            // see https://www.workerman.net/doc/workerman/async-tcp-connection/construct.html
//            'context' => [
//                'ssl' => [
//                    'verify_peer'      => false,
//                    'verify_peer_name' => false,
//                ],
//            ]
        ],
    ],
];
