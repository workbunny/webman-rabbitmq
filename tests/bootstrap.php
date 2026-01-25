<?php declare(strict_types=1);

if (!is_dir(__DIR__ . '/../config')) {
    \Workbunny\WebmanRabbitMQ\Install::install();
}

require_once __DIR__ . '/../vendor/workerman/webman-framework/src/support/bootstrap.php';


