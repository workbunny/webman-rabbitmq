<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Protocols;


abstract class AbstractProcess
{
    /** @var NacosClient  */
    protected NacosClient $_client;

    public function __construct()
    {
        $this->client = new NacosClient();
    }

    /**
     * @param Worker $worker
     * @return mixed
     */
    abstract public function onWorkerStart(Worker $worker);

    /**
     * @param Worker $worker
     * @return mixed
     */
    abstract public function onWorkerStop(Worker $worker);
}