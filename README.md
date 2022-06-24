<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-rabbitmq</p>**

**<p align="center">🐇 A PHP implementation of RabbitMQ Client for webman plugin. 🐇</p>**

# A PHP implementation of RabbitMQ Client for webman plugin


# 说明

本组件

## 使用

```
composer require workbunny/webman-rabbitmq
```

## 创建Builder

**Builder** 可以理解为类似 **ORM Model**，创建一个 **Builder** 就对应了一个队列；
使用该 **Builder** 对象进行 **publish()** 时，会向该队列投放消息；

创建多少个 **Builder** 就相当于创建了多少条队列；**注： 前提是将所创建的 Builder
加入了 webman 自定义进程配置 porcess.php**


- 继承FastBuilder
- 实现handler方法
- 重写属性【可选】

以下以 **TestBuilder** 举例：

```php
use Workbunny\WebmanRabbitMQ\FastBuilder;

class TestBuilder extends FastBuilder
{
    // QOS计数 【可选， 默认0】
    protected int $prefetch_count = 1;
    // QOS大小 【可选， 默认0】
    protected int $prefetch_size = 2;
    // 是否全局 【可选， 默认false】
    protected bool $is_global = true;
    
    public function handler(\Bunny\Message $message,\Bunny\Channel $channel,\Bunny\Client $client) : string{
        var_dump($message->content);
        return Constants::ACK;
        # Constants::NACK
        # Constants::REQUEUE
    }
}
```

## 实现消费

**消费是异步的，不会阻塞当前进程，不会影响webman/workerman的status；**

1. 将 **TestBuilder** 配置入 **Webman** 自定义进程中

```php
return [
    'test-builder' => [
        'handler' => \Examples\TestBuilder::class,
        'count'   => cpu_count(), # 建议与CPU数量保持一致，也可自定义
    ],
];
```

2. 启动 **webman** 后会自动创建queue、exchange并进行消费，连接数与配置的进程数 **count** 相同

## 实现生产

- 每个builder各包含一个连接，使用多个builder会创建多个连接

- 生产消息默认不关闭当前连接

- 异步生产的连接与消费者共用

### 同步生产

**该方法会阻塞等待至消息生产成功，返回bool**

- 向 **TestBuilder** 队列发布消息

```php
use Examples\TestBuilder;

$builder = TestBuilder::instance();
$message = $builder->getMessage();
$message->setBody('abcd');
$builder->syncConnection()->publish($message); # return bool
```

- 使用助手函数向 **TestBuilder** 发布消息

```php
use function Workbunny\WebmanRabbitMQ\sync_publish;
use Examples\TestBuilder;

sync_publish(TestBuilder::instance(), 'abc'); # return bool
```

### 异步生产

**该方法不会阻塞等待，立即返回 promise，可以利用 promise 进行 wait；也可以纯异步不等待**

- 向 **TestBuilder** 队列发布消息

```php
use Examples\TestBuilder;

$builder = TestBuilder::instance();
$message = $builder->getMessage();
$message->setBody('abcd');
$builder->connection()->publish($message); # return PromiseInterface|bool
```

- 使用助手函数向 **TestBuilder** 发布消息

```php
use function Workbunny\WebmanRabbitMQ\async_publish;
use Examples\TestBuilder;

async_publish(TestBuilder::instance(), 'abc'); # return PromiseInterface|bool
```