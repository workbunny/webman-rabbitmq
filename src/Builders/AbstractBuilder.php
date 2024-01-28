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
     * @var BuilderConfig
     */
    private BuilderConfig $_builderConfig;

    public function __construct()
    {
        $config = config('plugin.workbunny.webman-rabbitmq.app');
        // 复用连接
        if (self::$reuse = $config['reuse_connection'] ?? false) {
            if (!$this->getConnection()) {
                $this->setConnection(new Connection($config));
            }
        }
        // 非复用连接
        else {
            $this->setConnection(new Connection($config));
        }
        $this->setBuilderConfig(new BuilderConfig());
    }

    /**
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
     * @param string $class
     * @return void
     */
    public static function destroy(string $class): void
    {
        unset(self::$_builders[$class]);
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
     * @return Connection|null
     */
    public function getConnection(): ?Connection
    {
        return self::$_connection;
    }

    /**
     * @param Connection $connection
     */
    public function setConnection(Connection $connection): void
    {
        self::$_connection = $connection;
    }

    /**
     * @return bool
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