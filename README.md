<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-rabbitmq</p>**

**<p align="center">🐇 A PHP implementation of RabbitMQ Client for webman plugin. 🐇</p>**

# A PHP implementation of RabbitMQ Client for webman plugin


## 说明

## 创建Builder

```
composer require wokbunny/webman-rabbitmq
```

1. 创建一个builder

```php
use Workbunny\WebmanRabbitMQ\FastBuilder;

class TestBuilder extends FastBuilder
{

}
```

2. 重写参数【可选】

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
}
```

## 实现消费

1. 实现消费者处理函数

```php
use Workbunny\WebmanRabbitMQ\FastBuilder;
use Workbunny\WebmanRabbitMQ\Constants;

class TestBuilder extends FastBuilder
{
    public function handler(\Bunny\Message $message,\Bunny\Channel $channel,\Bunny\Client $client) : string{
        var_dump($message->content);
        return Constants::ACK;
        # Constants::NACK
        # Constants::REQUEUE
    }
}
```

2. 将 **TestBuilder** 配置入 **Webman** 自定义进程中

```php
return [
    'test-builder' => [
        'handler' => \Examples\TestBuilder::class,
        'count'   => cpu_count(), # 建议与CPU数量保持一致，也可自定义
    ],
];
```

3. **启动 webman**

## 实现生产

- 每个builder各包含一个连接，使用多个builder会创建多个连接

- 生产消息默认不关闭当前连接

- 异步生产的连接与消费者共用

### 同步生产

```php
$builder = \Examples\TestBuilder::instance();
$message = $builder->getMessage();
$message->setBody('abcd');
$builder->syncConnection()->publish($message); # return bool
```

### 异步生产

```php
$builder = \Examples\TestBuilder::instance();
$message = $builder->getMessage();
$message->setBody('abcd');
$builder->connection()->publish($message); # return PromiseInterface
```
