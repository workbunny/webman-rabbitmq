<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Protocols;

use Bunny\Channel as BunnyChannel;
use Bunny\Client as BunnyClient;
use Bunny\Message as BunnyMessage;
use Workerman\Worker;

interface BuilderInterface
{

    /**
     * @param Worker $worker
     * @return mixed
     */
    public function onWorkerStart(Worker $worker);

    /**
     * @param Worker $worker
     * @return mixed
     */
    public function onWorkerStop(Worker $worker);

    /**
     * @param Worker $worker
     * @return mixed
     */
    public function onWorkerReload(Worker $worker);

    /**
     * 消费响应
     * @param BunnyMessage $message
     * @param BunnyChannel $channel
     * @param BunnyClient $client
     * @return string
     */
    public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client) : string;
}