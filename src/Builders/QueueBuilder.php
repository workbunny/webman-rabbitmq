<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Workbunny\WebmanRabbitMQ\Constants;
use Workerman\Worker;

class QueueBuilder extends AbstractBuilder
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
    {}

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
     * ]
     */
    protected array \$queueConfigs = [
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
    
    /**
     * 【请勿移除该方法】
     * @param BunnyMessage \$message
     * @param BunnyChannel \$channel
     * @param BunnyClient \$client
     * @return string
     */
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