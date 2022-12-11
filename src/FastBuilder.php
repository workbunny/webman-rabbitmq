<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Bunny\Async\Client as BunnyClient;
use Bunny\Channel as BunnyChannel;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Protocols\AbstractMessage;
use Workbunny\WebmanRabbitMQ\Protocols\BuilderInterface;
use Workerman\Worker;

abstract class FastBuilder implements BuilderInterface
{
    public static bool $debug = false;

    protected int $prefetch_size = 0;

    protected int $prefetch_count = 0;

    protected bool $is_global = false;

    protected bool $delayed = false;

    /**
     * @var AbstractMessage|Message
     */
    private AbstractMessage $_message;

    /**
     * @var Connection|null
     */
    private ?Connection $_connection = null;

    /**
     * @var SyncConnection|null
     */
    private ?SyncConnection $_syncConnection = null;

    /**
     * @var FastBuilder[]
     */
    protected static array $_builders = [];

    /**
     * @return bool
     */
    public static function isDebug(): bool
    {
        return self::$debug;
    }

    /**
     * @return FastBuilder|static
     */
    public static function instance() : FastBuilder
    {
        if(!isset(self::$_builders[$class = get_called_class()])){
            self::$_builders[$class] = new $class();
        }
        return self::$_builders[$class];
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

        if($this->delayed){
            $exchangeType = $this->_message->getExchangeType();
            $this->_message->setExchangeType(Constants::DELAYED);
            $this->_message->setArguments([
                'x-delayed-type' => $exchangeType
            ]);
        }
    }

    /**
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker): void
    {
        $this->connection()->consume($this->getMessage());
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

        if($this->_syncConnection){
            $this->_syncConnection->close();
            $this->_syncConnection = null;
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
            $this->_connection = new Connection(self::isDebug() ? debug_config('plugin.workbunny.webman-rabbitmq.app') : null);
        }
        return $this->_connection;
    }

    /**
     * 获取同步生产者
     * @return SyncConnection
     */
    public function syncConnection() : SyncConnection
    {
        if(!$this->_syncConnection instanceof SyncConnection){
            $this->_syncConnection = new SyncConnection(self::isDebug() ? debug_config('plugin.workbunny.webman-rabbitmq.app') : null);
        }
        return $this->_syncConnection;
    }

    /**
     * @return Message|null
     */
    public function getMessage(): ?Message
    {
        return $this->_message;
    }

    /**
     * @param AbstractMessage $message
     * @return void
     */
    public function setMessage(AbstractMessage $message): void
    {
        $this->_message = $message;
        $this->_message->setCallback([$this, 'handler']);

        if($this->delayed){
            $exchangeType = $this->_message->getExchangeType();
            $this->_message->setExchangeType(Constants::DELAYED);
            $this->_message->setArguments([
                'x-delayed-type' => $exchangeType
            ]);
        }
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