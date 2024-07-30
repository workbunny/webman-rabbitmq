<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Bunny\Exception\ClientException;
use Workbunny\WebmanRabbitMQ\Constants;
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
        $name = str_replace('\\', '.', get_called_class());

        $this->getBuilderConfig()->setConsumerTag($this->exchangeName ?? $name);
        $this->getBuilderConfig()->setExchange($this->exchangeName ?? $name);
        $this->getBuilderConfig()->setExchangeType($this->exchangeType);

        $this->getBuilderConfig()->setQueue($this->queueConfig['name'] ?? $name);
        $this->getBuilderConfig()->setRoutingKey($this->queueConfig['routing_key'] ?? '');
        $this->getBuilderConfig()->setPrefetchCount($this->queueConfig['prefetch_count'] ?? 0);
        $this->getBuilderConfig()->setPrefetchSize($this->queueConfig['prefetch_size'] ?? 0);
        $this->getBuilderConfig()->setGlobal($this->queueConfig['is_global'] ?? false);
        $this->getBuilderConfig()->setCallback([$this, 'handler']);
        $this->getBuilderConfig()->setInit([$this, 'init']);
        if($this->queueConfig['delayed'] ?? false){
            $this->appendArguments([
                'x-delayed-type' => $this->getBuilderConfig()->getExchangeType()
            ]);
            $this->getBuilderConfig()->setExchangeType(Constants::DELAYED);
        }
        if($this->queueConfig['dead_letter'] ?? false){
            $this->appendArguments([
                'x-dead-letter-exchange' => $this->queueConfig['dead_letter']['exchange_name'],
                'x-dead-letter-routing-key'=>''
            ]);
        }
    }

    /**
     * @param array $arguments
     * @return void
     */
    public function appendArguments(array $arguments):void{
        $args = $this->getBuilderConfig()->getArguments();
        $args = array_merge($args,$arguments);
        $this->getBuilderConfig()->setArguments($args);
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        try {
            $this->getConnection()?->consume($this->getBuilderConfig());
        } catch (ClientException $exception) {
            $worker::log("Queue $worker->id exception: [{$exception->getCode()}] {$exception->getMessage()}, \n");
            sleep($this->restartInterval);
            $worker::stopAll();
        }
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void
    {
        if($this->getConnection()){
            $this->getConnection()->close($this->getConnection()->getAsyncClient());
            $this->getConnection()->close($this->getConnection()->getSyncClient());
        }
    }

    /** @inheritDoc */
    public function onWorkerReload(Worker $worker): void
    {
        $queue = $this->getBuilderConfig()->getQueue();
        echo "Consumer $worker->id [queue: $queue] reload. " . PHP_EOL;
    }

    /**
     * @param BunnyMessage $message
     * @param BunnyChannel $channel
     * @param BunnyClient $client
     * @return string
     */
    abstract public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client): string;

    /**
     * 初始化队列
     * @param BunnyChannel $channel
     * @return void
     */
    abstract public function init(BunnyChannel $channel): void;
    
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
    /**
     * @var array = [
     *   'name'           => 'example',
     *   'delayed'        => false,
     *   'prefetch_count' => 1,
     *   'prefetch_size'  => 0,
     *   'is_global'      => false,
     *   'routing_key'    => '',
     *   'dead_letter'=>[
     *       'exchange_name'=>'dlx.example'
     *    ],
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
        // 死信队列
        'dead_letter'=>[
        ],
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