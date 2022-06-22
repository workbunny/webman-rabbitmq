<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Psr\Log\LoggerInterface;

use Bunny\Client as BunnyClient;
use Bunny\Channel as BunnyChannel;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Protocols\AbstractMessage;
use Workbunny\WebmanRabbitMQ\Protocols\BuilderInterface;
use Workerman\Worker;

abstract class FastBuilder implements BuilderInterface
{
    protected int $prefetch_size = 0;

    protected int $prefetch_count = 0;

    protected bool $is_global = false;


    /**
     * @var AbstractMessage|Message
     */
    private AbstractMessage $_message;

    /**
     * @var Connection|null
     */
    private ?Connection $_connection = null;

    /**
     * @var SyncProducer|null
     */
    private ?SyncProducer $_syncProducer = null;

    protected static ?FastBuilder $_builder = null;

    /**
     * @return FastBuilder|static
     */
    public static function instance() : FastBuilder
    {
        if(self::$_builder instanceof FastBuilder){
            self::$_builder = new static();
        }
        return self::$_builder;
    }

    public function __construct()
    {
        $message['consumer_tag'] = $message['routing_key'] = $message['queue_name'] =
        $message['exchange_name'] = str_replace('\\', '.', get_called_class());

        $message['prefetch_size'] = $this->prefetch_size;
        $message['prefetch_count'] = $this->prefetch_count;
        $message['is_global'] = $this->is_global;

        $this->_message = new Message($message);
        $this->_message->setCallback([$this, 'handler']);
    }

    /**
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker): void
    {
        self::connection()->consume($this->_message);
    }

    /**
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStop(Worker $worker): void
    {
        if($this->_connection){
            $this->_connection->close();
            $this->_connection = null;
        }

        if($this->_syncProducer){
            $this->_syncProducer->close();
            $this->_syncProducer = null;
        }
    }

    /**
     * @param Worker $worker
     * @return void
     */
    public function onWorkerReload(Worker $worker): void
    {}

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
     * @return Message|null
     */
    public function getMessage(): ?Message
    {
        return $this->_message;
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