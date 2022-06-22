<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Psr\Log\LoggerInterface;

use Bunny\Client as BunnyClient;
use Bunny\Channel as BunnyChannel;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Protocols\AbstractMessage;

abstract class FastBuilder
{
    protected AbstractMessage $_message;

    protected ?LoggerInterface $_logger = null;

    protected ?Connection $_connection = null;

    protected ?SyncProducer $_syncProducer = null;

    /**
     * @param array $message = [
     *  'exchange_type'     => '',
     *  'routing_key'       => '',
     *  'consumer_tag'      => '',
     *  'prefetch_size'     => 0,
     *  'prefetch_count'    => 0,
     *  'is_global'         => false,
     * ]
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $message, ?LoggerInterface $logger = null)
    {
        $message['queue_name'] = $message['exchange_name'] = str_replace('\\', '.', get_called_class());
        $this->_message = new Message($message);
        $this->_message->setCallback([$this, 'handler']);

        $this->_logger = $logger;
    }

    /**
     * 获取连接
     * @return Connection
     */
    public function connection() : Connection
    {
        if(!$this->_connection instanceof Connection){
            $this->_connection = new Connection();
        }
        return $this->_connection;
    }

    /**
     * 获取同步生产者
     * @return SyncProducer
     */
    public function syncProducer() : SyncProducer
    {
        if(!$this->_syncProducer instanceof SyncProducer){
            $this->_syncProducer = new SyncProducer();
        }
        return $this->_syncProducer;
    }

    /**
     * 消费响应处理器
     * @param BunnyMessage $message
     * @param BunnyChannel $channel
     * @param BunnyClient $client
     * @return string
     */
    abstract public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client) : string;

}