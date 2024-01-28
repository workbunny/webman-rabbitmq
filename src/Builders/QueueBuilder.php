<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

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
        if($config['delayed'] ?? false){
            $this->getBuilderConfig()->setArguments([
                'x-delayed-type' => $this->getBuilderConfig()->getExchangeType()
            ]);
            $this->getBuilderConfig()->setExchangeType(Constants::DELAYED);
        }
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        $this->getConnection()?->consume($this->getBuilderConfig());
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

    /** @inheritDoc */
    public static function classContent(string $namespace, string $className, bool $isDelay): string
    {
        $isDelay = $isDelay ? 'true' : 'false';
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
     * ]
     */
    protected array \$queueConfigs = [
        'name'           => 'example',          // TODO 队列名称 ，默认由类名自动生成
        'delayed'        => $isDelay,           // TODO 是否延迟
        'prefetch_count' => 0,                  // TODO QOS 数量
        'prefetch_size'  => 0,                  // TODO QOS size 
        'is_global'      => false,              // TODO QOS 全局
        'routing_key'    => '',                 // TODO 路由键
    ];
    
    /** @var string 交换机类型 */
    protected string \$exchangeType = Constants::DIRECT; // TODO 交换机类型
    
    /** @var string|null 交换机名称 */
    protected ?string \$exchangeName = null; // TODO 交换机名称，默认由类名自动生成
    
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