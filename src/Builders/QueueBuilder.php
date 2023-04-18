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
    protected array $_queueConfig = [];

    /** @var string 交换机类型 */
    protected string $_exchangeType = Constants::DIRECT;

    /** @var string|null 交换机名称 */
    protected ?string $_exchangeName = null;

    public function __construct()
    {
        parent::__construct();
        $name = str_replace('\\', '.', get_called_class());

        $this->getBuilderConfig()->setConsumerTag($this->_exchangeName ?? $name);
        $this->getBuilderConfig()->setExchange($this->_exchangeName ?? $name);
        $this->getBuilderConfig()->setExchangeType($this->_exchangeType);

        $this->getBuilderConfig()->setQueue($this->_queueConfig['name'] ?? $name);
        $this->getBuilderConfig()->setRoutingKey($this->_queueConfig['routing_key'] ?? '');
        $this->getBuilderConfig()->setPrefetchCount($this->_queueConfig['prefetch_count'] ?? 0);
        $this->getBuilderConfig()->setPrefetchSize($this->_queueConfig['prefetch_size'] ?? 0);
        $this->getBuilderConfig()->setGlobal($this->_queueConfig['is_global'] ?? false);
        $this->getBuilderConfig()->setCallback([$this, 'handler']);
        if($config['delayed'] ?? false){
            $this->getBuilderConfig()->setArguments([
                'x-delayed-type' => $this->getBuilderConfig()->getExchangeType()
            ]);
            $this->getBuilderConfig()->setExchangeType(Constants::DELAYED);
        }
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker)
    {
        if($this->getConnection()){
            $this->getConnection()->consume($this->getBuilderConfig());
        }
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker)
    {
        if($this->getConnection()){
            $this->getConnection()->close($this->getConnection()->getAsyncClient());
            $this->getConnection()->close($this->getConnection()->getSyncClient());
        }
    }

    /** @inheritDoc */
    public function onWorkerReload(Worker $worker)
    {}

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
    protected array \$_queueConfigs = [
        'name'           => 'example',          // TODO 队列名称 ，默认由类名自动生成
        'delayed'        => $isDelay,           // TODO 是否延迟
        'prefetch_count' => 0,                  // TODO QOS 数量
        'prefetch_size'  => 0,                  // TODO QOS size 
        'is_global'      => false,              // TODO QOS 全局
        'routing_key'    => '',                 // TODO 路由键
    ];
    
    /** @var string 交换机类型 */
    protected string \$_exchangeType = Constants::DIRECT; // TODO 交换机类型
    
    /** @var string|null 交换机名称 */
    protected ?string \$_exchangeName = null; // TODO 交换机名称，默认由类名自动生成
    
    /**
     * 【请勿移除该方法】
     * @param BunnyMessage \$message
     * @param BunnyChannel \$channel
     * @param BunnyClient \$client
     * @return void
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