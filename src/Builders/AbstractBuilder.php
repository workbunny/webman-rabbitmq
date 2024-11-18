<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Connection;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workerman\Worker;
use function Workbunny\WebmanRabbitMQ\config;

abstract class AbstractBuilder
{
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
     * connection对象池
     *
     * @var Connection[]
     */
    private static array $_connections = [];

    /**
     * builder 名称
     *
     * @var string|null
     */
    private ?string $_builderName = null;

    /**
     * @var BuilderConfig
     */
    private BuilderConfig $_builderConfig;

    /**
     * 默认连接名
     *
     * @var string|null
     */
    protected ?string $connection = null;

    public function __construct()
    {
        $this->setBuilderName(get_called_class());
        self::$reuseConnection = config('plugin.workbunny.webman-rabbitmq.app.reuse_connection', false);
        self::$reuseChannel =config('plugin.workbunny.webman-rabbitmq.app.reuse_channel', false);
        $config = $this->connection
            ? config("plugin.workbunny.webman-rabbitmq.rabbitmq.connections.$this->connection", []) // 通过rabbitmq 配置文件配置
            : config('plugin.workbunny.webman-rabbitmq.app', []); // 兼容旧版配置
        if (!$config) {
            throw new WebmanRabbitMQException('RabbitMQ config not found. ');
        }
        $this->setConnection(new Connection($config));
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
     * 获取connections对象池
     *
     * @return Connection[]
     */
    public static function connections(): array
    {
        return self::$_connections;
    }

    /**
     * connection对象销毁
     *
     * @param string $builderName
     * @return void
     */
    public static function connectionDestroy(string $builderName): void
    {
        if (self::$_connections[$builderName] ?? null) {
            self::$_connections[$builderName]->disconnect(null);
        }
        unset(self::$_connections[$builderName]);
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
     * @return BuilderConfig
     */
    public function getBuilderConfig(): BuilderConfig
    {
        return $this->_builderConfig;
    }

    /**
     * @param BuilderConfig $builderConfig
     */
    public function setBuilderConfig(BuilderConfig $builderConfig): void
    {
        $this->_builderConfig = $builderConfig;
    }

    /**
     * 获取连接
     *
     * @return Connection|null
     */
    public function getConnection(): ?Connection
    {
        return self::$_connections[self::isReuseConnection() ? '' : $this->getBuilderName()] ?? null;
    }

    /**
     * 设置连接
     *
     * @param Connection $connection
     */
    public function setConnection(Connection $connection): void
    {
        self::$_connections[self::isReuseConnection() ? '' : $this->getBuilderName()] = $connection;
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