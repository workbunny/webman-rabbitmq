<?php
declare(strict_types=1);

use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQBuilder;
use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQRemove;
use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQList;
use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQClean;

return [
    WorkbunnyWebmanRabbitMQBuilder::class,
    WorkbunnyWebmanRabbitMQRemove::class,
    WorkbunnyWebmanRabbitMQClean::class,
    WorkbunnyWebmanRabbitMQList::class
];
