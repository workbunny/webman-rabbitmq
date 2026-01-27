<?php

declare(strict_types=1);

use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQBuilder;
use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQList;
use Workbunny\WebmanRabbitMQ\Commands\WorkbunnyWebmanRabbitMQRemove;

return [
    WorkbunnyWebmanRabbitMQBuilder::class,
    WorkbunnyWebmanRabbitMQRemove::class,
    WorkbunnyWebmanRabbitMQList::class,
];
