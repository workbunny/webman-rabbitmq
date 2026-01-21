<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connections\Traits;

use Bunny\AbstractClient;
use Bunny\Exception\ClientException;
use Workbunny\WebmanRabbitMQ\Clients\AsyncClient;
use Workbunny\WebmanRabbitMQ\Clients\CoClient;
use Workbunny\WebmanRabbitMQ\Clients\SyncClient;

trait ClientMethods
{
    /**
     * @var CoClient|AsyncClient|SyncClient|\Workerman\RabbitMQ\Client
     */
    protected AbstractClient $_consumer;

    /**
     * @var CoClient|AsyncClient|SyncClient|\Workerman\RabbitMQ\Client
     */
    protected AbstractClient $_publisher;

    /**
     * @param AbstractClient $client
     * @return void
     */
    protected function setConsumer(AbstractClient $client): void
    {
        $this->_consumer = $client;
    }

    /**
     * @param AbstractClient $client
     * @return void
     */
    protected function setPublisher(AbstractClient $client): void
    {
        $this->_publisher = $client;
    }

    /**
     * @return AbstractClient
     */
    public function getConsumer(): AbstractClient
    {
        return $this->_consumer;
    }

    /**
     * @return AbstractClient
     */
    public function getPublisher(): AbstractClient
    {
        return $this->_publisher;
    }
}
