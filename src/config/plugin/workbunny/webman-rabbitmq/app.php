<?php
return [
    'enable' => true,

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
    // 心跳回调
    'heartbeat_callback' => function(){
    },
    // 错误回调
    'error_callback'     => function(Throwable $throwable){
    },
    // 复用连接
    'reuse_connection'   => false,
    // 复用通道
    'reuse_channel'      => true,
    // AMQPS 如需使用AMQPS请取消注释
//    'ssl'                => [
//        'cafile'      => 'ca.pem',
//        'local_cert'  => 'client.cert',
//        'local_pk'    => 'client.key',
//    ],
];