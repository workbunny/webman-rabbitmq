<?php
declare(strict_types=1);

use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQBuilder;
use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQRemove;
use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQList;

return [
    WorkbunnyWebmanRabbitMQBuilder::class,
    WorkbunnyWebmanRabbitMQRemove::class,
    WorkbunnyWebmanRabbitMQList::class
];
