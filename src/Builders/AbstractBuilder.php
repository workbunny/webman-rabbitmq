<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Builders\Traits\BuilderConfigManagement;
use Workbunny\WebmanRabbitMQ\Builders\Traits\ConnectionsManagement;
use Workbunny\WebmanRabbitMQ\Connections\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workerman\Worker;
use function Workbunny\WebmanRabbitMQ\config;

abstract class AbstractBuilder
{
    use ConnectionsManagement;
    use BuilderConfigManagement;

    /**
     * @var bool
     */
    public static bool $debug = false;

    /**
     * @var bool
     */
    private static bool $reuseConnection = false;

    /**
     * @var bool
     */
    private static bool $reuseChannel = false;

    /**
     * builder对象池
     *
     * @var AbstractBuilder[]
     */
    private static array $_builders = [];

    /**
     * @var string[]
     */
    private static array $builderList = [
        'queue'     => QueueBuilder::class,
        'co-queue'  => CoQueueBuilder::class
    ];

    /**
     * builder 名称
     *
     * @var string|null
     */
    private ?string $_builderName = null;

    /**
     * 默认连接名
     *
     * @var string|null
     */
    protected ?string $connection = null;

    /**
     * @var array
     */
    protected array $config = [];

    public function __construct()
    {
        $this->setBuilderName(get_called_class());
        self::$reuseConnection = config('plugin.workbunny.webman-rabbitmq.app.reuse_connection', false);
        self::$reuseChannel = config('plugin.workbunny.webman-rabbitmq.app.reuse_channel', false);
        $this->config = $this->connection
            ? config("plugin.workbunny.webman-rabbitmq.rabbitmq.connections.$this->connection", []) // 通过rabbitmq 配置文件配置
            : config('plugin.workbunny.webman-rabbitmq.app', []); // 兼容旧版配置
        if (!$this->config) {
            throw new WebmanRabbitMQException('RabbitMQ config not found. ');
        }
        $this->setBuilderConfig(new BuilderConfig());
    }

    /**
     * 是否复用连接
     *
     * @return bool
     */
    public static function isReuseConnection(): bool
    {
        return self::$reuseConnection;
    }

    /**
     * 是否复用channel
     *
     * @return bool
     */
    public static function isReuseChannel(): bool
    {
        return self::$reuseChannel;
    }

    /**
     * builder单例
     *
     * @return AbstractBuilder
     */
    public static function instance(): AbstractBuilder
    {
        if (!(self::$_builders[$class = get_called_class()] ?? null)) {
            self::$_builders[$class] = new $class();
        }
        return self::$_builders[$class];
    }

    /**
     * 获取builder对象池
     *
     * @return AbstractBuilder[]
     */
    public static function builders(): array
    {
        return self::$_builders;
    }

    /**
     * 销毁指定builder
     *
     * @param string $builderName
     * @return void
     */
    public static function destroy(string $builderName): void
    {
        self::connectionDestroy($builderName);
        unset(self::$_builders[$builderName]);
    }

    /**
     * 通过mode获取builder类名
     *
     * @param string $mode
     * @return string|null
     */
    public static function getBuilderClass(string $mode): ?string
    {
        return static::$builderList[$mode] ?? null;
    }

    /**
     * @return string|null
     */
    public function getBuilderName(): ?string
    {
        return $this->_builderName;
    }

    /**
     * @param string|null $builderName
     */
    public function setBuilderName(?string $builderName): void
    {
        $this->_builderName = $builderName;
    }

    /**
     * 获取连接
     *
     * @return ConnectionInterface|null
     */
    public function getConnection(): ?ConnectionInterface
    {
        return static::connectionGet(self::isReuseConnection() ? '' : $this->getBuilderName());
    }

    /**
     * 设置连接
     *
     * @param ConnectionInterface $connection
     */
    public function setConnection(ConnectionInterface $connection): void
    {
        static::connectionSet(self::isReuseConnection() ? '' : $this->getBuilderName(), $connection);
    }

    /**
     * Builder 启动时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerStart(Worker $worker): void;

    /**
     * Builder 停止时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerStop(Worker $worker): void;

    /**
     * Builder 重加载时
     *
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerReload(Worker $worker): void;

    /**
     * Command 获取需要创建的类文件内容
     *
     * @param string $namespace
     * @param string $className
     * @param bool $isDelay
     * @return string
     */
    abstract public static function classContent(string $namespace, string $className, bool $isDelay): string;
}