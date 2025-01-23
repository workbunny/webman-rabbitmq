<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Clients;

use Workbunny\WebmanRabbitMQ\Clients\Traits\ClientMethods;
use Workerman\RabbitMQ\Clients\CoroutineClient;

class CoClient extends CoroutineClient
{
    use ClientMethods;
}