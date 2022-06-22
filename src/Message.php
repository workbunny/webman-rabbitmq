<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Workbunny\WebmanRabbitMQ\Protocols\AbstractMessage;

class Message extends AbstractMessage {

    /**
     * @param array $message = [
     *  'exchange_name'     => '',
     *  'exchange_type'     => '',
     *  'queue_name'        => '',
     *  'routing_key'       => '',
     *  'consumer_tag'      => '',
     *  'prefetch_size'     => 0,
     *  'prefetch_count'    => 0,
     *  'is_global'         => false,
     * ]
     */
    public function __construct(array $message)
    {
        $this->setExchange($message['exchange_name'] ?? $this->getExchange());
        $this->setExchangeType($message['exchange_type'] ?? $this->getExchangeType());

        $this->setQueue($message['queue_name'] ?? $this->getQueue());
        $this->setRoutingKey($message['routing_key'] ?? $this->getRoutingKey());

        $this->setConsumerTag($message['consumer_tag'] ?? $this->getConsumerTag());

        $this->setPrefetchSize($message['prefetch_size'] ?? $this->getPrefetchSize());
        $this->setPrefetchCount($message['prefetch_count'] ?? $this->getPrefetchCount());
        $this->setGlobal($message['is_global'] ?? false);
    }
}