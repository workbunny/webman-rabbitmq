<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Clients;

use Workbunny\WebmanRabbitMQ\Traits\ClientMethods;
use Workerman\RabbitMQ\Clients\CoroutineClient;

abstract class AbstractClient extends CoroutineClient
{
    use ClientMethods;

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}