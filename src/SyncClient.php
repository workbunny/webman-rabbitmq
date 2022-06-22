<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Bunny\Client;

class SyncClient extends Client {
    public function __destruct()
    {
        try {
            parent::__destruct();
        }catch (\Throwable $throwable){}
    }
}