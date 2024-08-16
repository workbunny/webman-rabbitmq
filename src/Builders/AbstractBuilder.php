<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders;

use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Connection;
use Workerman\Worker;
use function Workbunny\WebmanRabbitMQ\config;

abstract class AbstractBuilder
{
    public static bool $debug = false;

    /**
     * @var bool
     * @deprecated
     */
    private static bool $reuse = false;

    /**
     * @var AbstractBuilder[]
     */
    private static array $_builders = [];

    /**
     * @var Connection|null
     */
    private static ?Connection $_connection = null;

    /**
     * @var array
     */
    private array $_config = [];

    /**
     * @var BuilderConfig
     */
    private BuilderConfig $_builderConfig;

    public function __construct()
    {
        $this->_config = config('plugin.workbunny.webman-rabbitmq.app');
        self::$reuse = $this->_config['reuse_connection'] ?? false;
        $this->setConnection(new Connection($this->_config));
        $this->setBuilderConfig(new BuilderConfig());
    }

    /**
     * builder单例
     *
     * @return AbstractBuilder
     */
    public static function instance(): AbstractBuilder
    {
        if(!isset(self::$_builders[$class = get_called_class()])){
            self::$_builders[$class] = new $class();
        }
        return self::$_builders[$class];
    }

    /**
     * 销毁指定builder
     *
     * @param string $class
     * @return void
     */
    public static function destroy(string $class): void
    {
        unset(self::$_builders[$class]);
    }

    /**
     * 获取当前进程所有builders
     *
     * @return AbstractBuilder[]
     */
    public static function builders(): array
    {
        return self::$_builders;
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
        return self::$_connection;
    }

    /**
     * 设置连接
     *
     * @param Connection $connection
     */
    public function setConnection(Connection $connection): void
    {
        self::$_connection = $connection;
    }

    /**
     * @return bool
     * @deprecated
     */
    public static function isReuse(): bool
    {
        return self::$reuse;
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