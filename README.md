<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-rabbitmq</p>**

**<p align="center">🐇 A PHP implementation of RabbitMQ Client for webman plugin. 🐇</p>**

# A PHP implementation of RabbitMQ Client for webman plugin


[![Latest Stable Version](http://poser.pugx.org/workbunny/webman-rabbitmq/v)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![Total Downloads](http://poser.pugx.org/workbunny/webman-rabbitmq/downloads)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![Latest Unstable Version](http://poser.pugx.org/workbunny/webman-rabbitmq/v/unstable)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![License](http://poser.pugx.org/workbunny/webman-rabbitmq/license)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![PHP Version Require](http://poser.pugx.org/workbunny/webman-rabbitmq/require/php)](https://packagist.org/packages/workbunny/webman-rabbitmq)

## 常见问题

1. 什么时候使用消息队列？

	**当你需要对系统进行解耦、削峰、异步的时候；如发送短信验证码、秒杀活动、资产的异步分账清算等。**

2. RabbitMQ和Redis的区别？

	**Redis中的Stream的特性同样适用于消息队列，并且也包含了比较完善的ACK机制，但在一些点上与RabbitMQ存在不同：**
	- **Redis Stream没有完善的后台管理；RabbitMQ拥有较为完善的后台管理及Api；**
	- **Redis的持久化策略取舍：默认的RDB策略极端情况下存在丢失数据，AOF策略则需要牺牲一些性能；Redis持久化方案更多，可对消息持久化也可对队列持久化；**
	- **RabbitMQ拥有更多的插件可以提供更完善的协议支持及功能支持；**

3. 什么时候使用Redis？什么时候使用RabbitMQ？

	**当你的队列使用比较单一或者比较轻量的时候，请选用 Redis Stream；当你需要一个比较完整的消息队列体系，包括需要利用交换机来绑定不同队列做一些比较复杂的消息任务的时候，请选择RabbitMQ；**

	**当然，如果你的队列使用也比较单一，但你需要用到一些管理后台相关系统化的功能的时候，又不想花费太多时间去开发的时候，也可以使用RabbitMQ；因为RabbitMQ提供了一整套后台管理的体系及 HTTP API 供开发者兼容到自己的管理后台中，不需要再消耗多余的时间去开发功能；**

	注：这里的 **轻量** 指的是 **无须将应用中的队列服务独立化，该队列服务是该应用独享的**

## 简介

RabbitMQ的webman客户端插件；

异步无阻塞消费、异步无阻塞生产、同步阻塞生产；

简单易用高效，可以轻易的实现master/worker的队列模式（一个队列多个消费者）；


## 安装

```
composer require workbunny/webman-rabbitmq
```

## 配置

```php
<?php
return [
    'enable' => true,

    'host'               => '127.0.0.1',
    'vhost'              => '/',
    'port'               => 5672,
    'username'           => 'guest',
    'password'           => 'guest',
    'mechanism'          => 'AMQPLAIN', # 阿里云等云服务使用 PLAIN
    'timeout'            => 10,
    'heartbeat'          => 50,
    'heartbeat_callback' => function(){ # 心跳回调
    },
    'error_callback'     => function(Throwable $throwable){ # 异常回调
    }
];
```

## 使用

### 创建Builder

**Builder** 可以理解为类似 **ORM** 的 **Model**，创建一个 **Builder** 就对应了一个队列；
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

### 实现消费

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

### 实现生产

- 每个builder各包含一个连接，使用多个builder会创建多个连接

- 生产消息默认不关闭当前连接

- 异步生产的连接与消费者共用

#### 同步生产

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

#### 异步生产

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

### 实现延迟队列

延迟队列需要借助RabbitMQ的插件实现，所以需要先给RabbitMQ安装相关支撑插件。

#### 安装插件

1. 进入 rabbitMQ 的 plugins 目录下执行命令下载插件（以rabbitMQ 3.8.x举例）：

```shell
wget https://github.com/rabbitmq/rabbitmq-delayed-message-exchange/releases/download/3.8.17/rabbitmq_delayed_message_exchange-3.8.17.8f537ac.ez
```

2. 执行安装命令

```shell
rabbitmq-plugins enable rabbitmq_delayed_message_exchange
```

#### 使用方法

1. 继承重写 **Builder** 的 **delayed** 属性：

```php
use Workbunny\WebmanRabbitMQ\FastBuilder;

class DelayBuilder extends FastBuilder
{
    protected bool $delayed = true;
    
    public function handler(\Bunny\Message $message,\Bunny\Channel $channel,\Bunny\Client $client) : string{
        var_dump($message->content);
        return Constants::ACK;
        # Constants::NACK
        # Constants::REQUEUE
    }
}
```

2. 生产者添加自定义头部 **x-delay** 实现延迟消息，单位毫秒：

   - 同步生产
     ```php
     use Examples\DelayBuilder;

     $builder = DelayBuilder::instance();
     $message = $builder->getMessage();
     $message->setBody('abcd');
     $message->setHeaders(array_merge($message->getHeaders(), [
         'x-delay' => 10000, # 延迟10秒
     ]));
     $builder->syncConnection()->publish($message); # return bool
     ```

     ```php
     use function Workbunny\WebmanRabbitMQ\sync_publish;
     use Examples\DelayBuilder;

     sync_publish(DelayBuilder::instance(), 'abc', [
         'x-delay' => 10000, # 延迟10秒
     ]); # return bool
     ```
   - 异步同理

3. 将 **DelayBuilder** 加入 webman 的 process.php 配置中，启动 webman


## 说明
- 目前这套代码在我司生产环境运行，我会做及时的维护，**欢迎 [issue](https://github.com/workbunny/webman-rabbitmq/issues) 和 PR**；
- **Message** 可以理解为队列、交换机的配置信息；
- 继承实现 **AbstractMessage** 可以自定义Message；
- **Builder** 可通过 **Builder->setMessage()** 可设置自定义配置；
- 可使用 **SyncClient** 或 **AsyncClient** 自行实现一些自定义消费/自定义生产的功能；