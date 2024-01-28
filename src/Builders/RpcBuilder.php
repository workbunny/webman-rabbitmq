<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Bunny\Async\Client as BunnyClient;
use Bunny\Channel as BunnyChannel;
use Bunny\Message as BunnyMessage;
use Workerman\Worker;

// TODO
abstract class RpcBuilder extends AbstractBuilder
{

    public function onWorkerStart(Worker $worker): void
    {
        // TODO: Implement onWorkerStart() method.
    }

    public function onWorkerStop(Worker $worker): void
    {
        // TODO: Implement onWorkerStop() method.
    }

    public function onWorkerReload(Worker $worker): void
    {
        // TODO: Implement onWorkerReload() method.
    }

    public static function classContent(string $namespace, string $className, bool $isDelay): string
    {
        // TODO: Implement classContent() method.
        return '';
    }

    /**
     * @param BunnyMessage $message
     * @param BunnyChannel $channel
     * @param BunnyClient $client
     * @return string
     */
    abstract public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client): string;
}