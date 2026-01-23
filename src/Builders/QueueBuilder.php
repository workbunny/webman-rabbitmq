<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workerman\Worker;
use Bunny\Channel as BunnyChannel;
use Bunny\Async\Client as BunnyClient;
use Bunny\Message as BunnyMessage;

abstract class QueueBuilder extends AbstractBuilder
{
    /**
     * 队列配置
     *
     * @var array = [
     *  'name'           => 'example',
     *  'delayed'        => false,
     *  'prefetch_count' => 1,
     *  'prefetch_size'  => 0,
     *  'is_global'      => false,
     *  'routing_key'    => '',
     * ]
     */
    protected array $queueConfig = [];

    /** @var string 交换机类型 */
    protected string $exchangeType = Constants::DIRECT;

    /** @var string|null 交换机名称 */
    protected ?string $exchangeName = null;

    /** @var int|null 重启间隔 */
    protected ?int $restartInterval = 5;

    public function __construct()
    {
        parent::__construct();
        $name = $this->getBuilderName();
        $this->getBuilderConfig()->setConsumerTag($this->exchangeName ?? $name);
        $this->getBuilderConfig()->setExchange($this->exchangeName ?? $name);
        $this->getBuilderConfig()->setExchangeType($this->exchangeType);

        $this->getBuilderConfig()->setQueue($this->queueConfig['name'] ?? $name);
        $this->getBuilderConfig()->setRoutingKey($this->queueConfig['routing_key'] ?? '');
        $this->getBuilderConfig()->setPrefetchCount($this->queueConfig['prefetch_count'] ?? 0);
        $this->getBuilderConfig()->setPrefetchSize($this->queueConfig['prefetch_size'] ?? 0);
        $this->getBuilderConfig()->setGlobal($this->queueConfig['is_global'] ?? false);
        $this->getBuilderConfig()->setCallback([$this, 'handler']);
        if($this->queueConfig['delayed'] ?? false){
            $this->getBuilderConfig()->setArguments([
                'x-delayed-type' => $this->getBuilderConfig()->getExchangeType()
            ]);
            $this->getBuilderConfig()->setExchangeType(Constants::DELAYED);
        }
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        try {
            $this->connection()?->consume($this->getBuilderConfig());
        } catch (WebmanRabbitMQException $exception) {
            $this->logger?->notice("Queue $worker->id exception, retry after $this->restartInterval seconds. ", [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile() . ':' . $exception->getLine(),
            ]);
            sleep($this->restartInterval);
            $worker::stopAll();
        }
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void
    {
        self::getConnections()->closeConnections();
    }

    /** @inheritDoc */
    public function onWorkerReload(Worker $worker): void
    {
        $queue = $this->getBuilderConfig()->getQueue();
        $this->logger?->notice("Consumer $worker->id [queue: $queue] reload.");
    }

    /**
     * @param BunnyMessage $message
     * @param BunnyChannel $channel
     * @param BunnyClient $client
     * @return string
     */
    abstract public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client): string;

    /** @inheritDoc */
    public static function classContent(string $namespace, string $className, bool $isDelay): string
    {
        $isDelay = $isDelay ? 'true' : 'false';
        $name = str_replace('\\', '.', "$namespace.$className");
        return <<<doc
<?php declare(strict_types=1);

namespace $namespace;

use Bunny\Channel as BunnyChannel;
use Bunny\Async\Client as BunnyClient;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\Builders\QueueBuilder;

class $className extends QueueBuilder
{
    /** @inheritdoc  */
    protected ?string \$connection = 'rabbitmq';
    
    /**
     * @var array = [
     *   'name'           => 'example',
     *   'delayed'        => false,
     *   'prefetch_count' => 1,
     *   'prefetch_size'  => 0,
     *   'is_global'      => false,
     *   'routing_key'    => '',
     * ]
     */
    protected array \$queueConfig = [
        // 队列名称 ，默认由类名自动生成
        'name'           => '$name',
        // 是否延迟          
        'delayed'        => $isDelay,
        // QOS 数量
        'prefetch_count' => 0,
        // QOS size 
        'prefetch_size'  => 0,
        // QOS 全局
        'is_global'      => false,
        // 路由键
        'routing_key'    => '',
    ];
    
    /** @var string 交换机类型 */
    protected string \$exchangeType = Constants::DIRECT;
    
    /** @var string|null 交换机名称,默认由类名自动生成 */
    protected ?string \$exchangeName = '$name';
    
    /** @inheritDoc */
    public function handler(BunnyMessage \$message, BunnyChannel \$channel, BunnyClient \$client): string 
    {
        // TODO 请重写消费逻辑
        echo "请重写 $className::handler\\n";
        return Constants::ACK;
    }
}
doc;
    }
}