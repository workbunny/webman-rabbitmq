<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-rabbitmq</p>**

**<p align="center">🐇 A PHP implementation of RabbitMQ Client for webman plugin. 🐇</p>**

# A PHP implementation of RabbitMQ Client for webman plugin


[![Latest Stable Version](https://badgen.net/packagist/v/workbunny/webman-rabbitmq/latest)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![Total Downloads](https://badgen.net/packagist/dt/workbunny/webman-rabbitmq)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![License](https://badgen.net/packagist/license/workbunny/webman-rabbitmq)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![PHP Version Require](https://badgen.net/packagist/php/workbunny/webman-rabbitmq)](https://packagist.org/packages/workbunny/webman-rabbitmq)

## 常见问题

1. 什么时候使用消息队列？

   **当你需要对系统进行解耦、削峰、异步的时候；如发送短信验证码、秒杀活动、资产的异步分账清算等。**

2. RabbitMQ和Redis的区别？

   **Redis中的Stream的特性同样适用于消息队列，并且也包含了比较完善的ACK机制，但在一些点上与RabbitMQ存在不同：**
	- **Redis Stream没有完善的后台管理；RabbitMQ拥有较为完善的后台管理及Api；**
	- **Redis的持久化策略取舍：默认的RDB策略极端情况下存在丢失数据，AOF策略则需要牺牲一些性能；RabbitMQ持久化方案更多，可对消息持久化也可对队列持久化；**
	- **RabbitMQ拥有更多的插件可以提供更完善的协议支持及功能支持；**

3. 什么时候使用Redis？什么时候使用RabbitMQ？

   **当你的队列使用比较单一或者比较轻量的时候，请选用 Redis Stream；当你需要一个比较完整的消息队列体系，包括需要利用交换机来绑定不同队列做一些比较复杂的消息任务的时候，请选择RabbitMQ；**

   **当然，如果你的队列使用也比较单一，但你需要用到一些管理后台相关系统化的功能的时候，又不想花费太多时间去开发的时候，也可以使用RabbitMQ；因为RabbitMQ提供了一整套后台管理的体系及 HTTP API 供开发者兼容到自己的管理后台中，不需要再消耗多余的时间去开发功能；**

   注：这里的 **轻量** 指的是 **无须将应用中的队列服务独立化，该队列服务是该应用独享的**

## 简介

RabbitMQ的webman客户端插件；

异步无阻塞消费、异步无阻塞生产、同步阻塞生产；

简单易用高效，可以轻易的实现master/worker的队列模式（一个队列多个消费者）；

支持延迟队列；


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

- **创建一个消费者进程数量为1的普通队列：（在项目根目录执行）**
```shell
./webman workbunny:rabbitmq-builder test 1
```

- **创建一个消费者进程数量为1的延迟队列：（在项目根目录执行）**
```shell
./webman workbunny:rabbitmq-builder test 1 -d
	
# 或
	
./webman workbunny:rabbitmq-builder test 1 --delayed
```

- **命令支持二级菜单**
```shell
# 在 process/workbunny/rabbitmq/project 目录下创建 TestBuilder.php
./webman workbunny:rabbitmq-builder project/test 1

# 延迟同理
```

**注：延迟队列需要为 rabbitMQ 安装 rabbitmq_delayed_message_exchange 插件**

1. 进入 rabbitMQ 的 plugins 目录下执行命令下载插件（以rabbitMQ 3.8.x举例）：
```shell
wget https://github.com/rabbitmq/rabbitmq-delayed-message-exchange/releases/download/3.8.17/rabbitmq_delayed_message_exchange-3.8.17.8f537ac.ez
```

2. 执行安装命令
```shell
rabbitmq-plugins enable rabbitmq_delayed_message_exchange
```

#### 说明：

- **Builder** 可以理解为类似 **ORM** 的 **Model**，创建一个 **Builder** 就对应了一个队列；使用该 **Builder** 对象进行 **publish()** 时，会向该队列投放消息；创建多少个 **Builder** 就相当于创建了多少条队列；

- **命令结构：**
```shell
workbunny:rabbitmq-builder [-d|--delayed] [--] <name> <count>

# 【必填】 name：Builder名称
# 【必填】count：启动的消费者进程数量
# 【选填】-d/--delayed：是否是延迟队列
```

- 在项目根目录下命令会在 **process/workbunny/rabbitmq** 路径下创建一个Builder，并且将该Builder自动加入 **config/plugin/workbunny/webman-rabbitmq/process.php** 配置中作为自定义进程启动；**（如不需要自动加载消费者进程，请自行注释该配置）**；

- 消费是异步的，不会阻塞当前进程，不会影响 **webman/workerman** 的 **status**；


- **Builder文件结构入下，可自行调整类属性：**
```php
<?php
declare(strict_types=1);

namespace process\workbunny\rabbitmq;

use Bunny\Channel as BunnyChannel;
use Bunny\Async\Client as BunnyClient;
use Bunny\Message as BunnyMessage;
use Workbunny\WebmanRabbitMQ\Constants;
use Workbunny\WebmanRabbitMQ\FastBuilder;

class TestBuilder extends FastBuilder
{
   	// QOS 大小
   	protected int $prefetch_size = 0;
   	// QOS 数量
   	protected int $prefetch_count = 0;
   	// QOS 是否全局
   	protected bool $is_global = false;
   	// 是否延迟队列
   	protected bool $delayed = false;
   	// 消费回调
   	public function handler(BunnyMessage $message, BunnyChannel $channel, BunnyClient $client): string
   	{
       	// TODO 消费需要的回调逻辑
       	var_dump('请重写 TestBuilderDelayed::handler() ');
       	return Constants::ACK;
       	# Constants::NACK
       	# Constants::REQUEUE
   	}
}
```

### 移除Builder

- **移除名为 test 的普通队列：（在项目根目录执行）**

```shell
./webman workbunny:rabbitmq-remove test
```

- **移除名为 test 的延迟队列：（在项目根目录执行）**
```shell
./webman workbunny:rabbitmq-remove test -d
# 或
./webman workbunny:rabbitmq-remove test --delayed
```

- **仅关闭名为 test 的普通队列：（在项目根目录执行）**
```shell
./webman workbunny:rabbitmq-remove test -c
# 或
./webman workbunny:rabbitmq-remove test --close
```

### 查看Builder

```shell
./webman workbunny:rabbitmq-list
```

**注：当 Builder 未启动时，handler 与 count 显示为 --**

```shell
+----------+---------------------------------------------------------------------------+-------------------------------------------------+-------+
| name     | file                                                                      | handler                                         | count |
+----------+---------------------------------------------------------------------------+-------------------------------------------------+-------+
| test     | /var/www/your-project/process/workbunny/rabbitmq/TestBuilder.php          | process\workbunny\rabbitmq\TestBuilder          | 1     |
| test -d  | /var/www/your-project/process/workbunny/rabbitmq/TestBuilderDelayed.php   | process\workbunny\rabbitmq\TestBuilderDelayed   | 1     |
+----------+---------------------------------------------------------------------------+-------------------------------------------------+-------+
```

### 生产

- 每个builder各包含一个连接，使用多个builder会创建多个连接

- 生产消息默认不关闭当前连接

- 异步生产的连接与消费者共用

#### 1. 同步发布消息

**该方法会阻塞等待至消息生产成功，返回bool**

- 发布普通消息

**注：向延迟队列发布普通消息会抛出一个 WebmanRabbitMQException 异常**

```php
use function Workbunny\WebmanRabbitMQ\sync_publish;
use process\workbunny\rabbitmq\TestBuilder;

sync_publish(TestBuilder::instance(), 'abc'); # return bool
```

- 发布延迟消息

**注：向普通队列发布延迟消息会抛出一个 WebmanRabbitMQException 异常**

```php
use function Workbunny\WebmanRabbitMQ\sync_publish;
use process\workbunny\rabbitmq\TestBuilder;

sync_publish(TestBuilder::instance(), 'abc', [
	'x-delay' => 10000, # 延迟10秒
]); # return bool
```

#### 2. 异步发布消息

**该方法不会阻塞等待，立即返回 [React\Promise](https://github.com/reactphp/promise)，
可以利用 [React\Promise](https://github.com/reactphp/promise) 进行 wait；
也可以纯异步不等待，[React\Promise 项目地址](https://github.com/reactphp/promise)；**
- 发布普通消息

**注：向延迟队列发布普通消息会抛出一个 WebmanRabbitMQException 异常**

```php
use function Workbunny\WebmanRabbitMQ\async_publish;
use process\workbunny\rabbitmq\TestBuilder;

async_publish(TestBuilder::instance(), 'abc'); # return PromiseInterface|bool
```

- 发布延迟消息

**注：向普通队列发布延迟消息会抛出一个 WebmanRabbitMQException 异常**

```php
use function Workbunny\WebmanRabbitMQ\async_publish;
use process\workbunny\rabbitmq\TestBuilder;

async_publish(TestBuilder::instance(), 'abc', [
	'x-delay' => 10000, # 延迟10秒
]); # return PromiseInterface|bool
```

## 说明
- **生产可用，欢迎 [issue](https://github.com/workbunny/webman-rabbitmq/issues) 和 PR**；
- **Message** 可以理解为队列、交换机的配置信息；
- 继承实现 **AbstractMessage** 可以自定义Message；
- **Builder** 可通过 **Builder->setMessage()** 可设置自定义配置；
- 可使用 **SyncClient** 或 **AsyncClient** 自行实现一些自定义消费/自定义生产的功能；
