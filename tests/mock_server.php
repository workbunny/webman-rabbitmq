<?php
declare(strict_types=1);

use Workerman\Connection\TcpConnection;
use Workerman\Worker;
require_once dirname(__DIR__) . '/vendor/autoload.php';

$worker = new Worker('tcp://0.0.0.0:5672');
$worker->name = 'mock_server';
$worker->onMessage = function (TcpConnection $connection, $data){
    dump($data);
    $connection->send($data);
};
Worker::runAll();