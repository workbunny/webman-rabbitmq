<?php declare(strict_types=1);

return [
    'connections' => [
        'rabbitmq' => [
            'host'               => 'rabbitmq',
            'vhost'              => '/',
            'port'               => 5672,
            'username'           => 'guest',
            'password'           => 'guest',
            'mechanism'          => 'AMQPLAIN',
            'timeout'            => 10,
            // 重启间隔
            'restart_interval'   => 0,
            // 心跳间隔
            'heartbeat'          => 50,
            'lazy_connect'       => false,
            // 消费者
            'consumer' => [
                'reuse' => true, // 复用
                'wait_min' => 10, // 最小间隔
                'wait_max' => 90, // 最大间隔
            ],
            // 生产者
            'producer' => [
                'reuse' => true,
                'wait_min' => 10,
                'wait_max' => 90,
            ],
            // 连接池
            'pool' => [
                'min_connections'  => 1,
                'max_connections'  => 10,
                'idle_timeout'     => 60,
                'wait_timeout'     => 10
            ],
            // 心跳回调 callable
            'heartbeat_callback' => null,
        ]
    ]
];