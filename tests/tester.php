<?php

use Workerman\Events\Swow;
use Workerman\Events\Swoole;
use Workerman\Events\Fiber;
use Workerman\Timer;
use Workerman\Worker;

require_once __DIR__ . '/../vendor/autoload.php';


if (class_exists(Revolt\EventLoop::class) && (DIRECTORY_SEPARATOR === '/' || !extension_loaded('swow'))) {
    create_test_worker(function () {
        (new PHPUnit\TextUI\Application)->run([
            __DIR__ . '/../vendor/bin/phpunit',
            '--colors=always',
            ...glob(__DIR__ . '/*Test.php')
        ]);
    }, Fiber::class);
}


if (extension_loaded('Swoole')) {
    create_test_worker(function () {
        (new PHPUnit\TextUI\Application)->run([
            __DIR__ . '/../vendor/bin/phpunit',
            '--colors=always',
            ...glob(__DIR__ . '/*Test.php')
        ]);
    }, Swoole::class);
}

if (extension_loaded('Swow')) {
    create_test_worker(function () {
        (new PHPUnit\TextUI\Application)->run([
            __DIR__ . '/../vendor/bin/phpunit',
            '--colors=always',
            ...glob(__DIR__ . '/*Test.php')
        ]);
    }, Swow::class);
}

function create_test_worker(Closure $callable, $eventLoopClass): void
{
    $worker = new Worker();

    $worker->eventLoop = $eventLoopClass;
    $worker->onWorkerStart = function () use ($callable, $eventLoopClass) {
        $fp = fopen(__FILE__, 'r+');
        flock($fp, LOCK_EX);
        echo PHP_EOL . PHP_EOL. PHP_EOL . '[TEST EVENT-LOOP: ' . basename(str_replace('\\', '/', $eventLoopClass)) . ']' . PHP_EOL;
        try {
            $callable();
        } catch (Throwable $e) {
            echo $e;
        } finally {
            flock($fp, LOCK_UN);
        }
        Timer::repeat(1, function () use ($fp) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                if(function_exists('posix_kill')) {
                    posix_kill(posix_getppid(), SIGINT);
                } else {
                    Worker::stopAll();
                }
            }
        });
    };
}
Worker::$logFile = 'tester.log';
Worker::runAll();
