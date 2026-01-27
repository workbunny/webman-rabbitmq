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

### 说明

- 此文档为3.0
- 2.0 LTS [2.0文档](https://github.com/workbunny/webman-rabbitmq/blob/2.x/README.md)

## 简介

RabbitMQ的webman客户端插件；

1. 支持5种消费模式：简单队列、workQueue、routing、pub/sub、exchange；
2. 支持延迟队列（rabbitMQ须安装插件）；
3. 异步无阻塞消费、异步无阻塞生产、同步阻塞生产；

### 概念

```
    ┌───────────┐                              
    | Builder A | ──┐          
    └───────────┘   |                                          | ┌───────────┐
                    |                                          | | Channel 1 |
                    |                                          | └───────────┘
    ┌───────────┐   └─> ┌──────────────────┐                   | ┌───────────┐
    | Builder A | ────> | Connections Pool | ── connection ──> | | Channel 2 |
    └───────────┘   ┌─> └──────────────────┘   min ... MAX     | └───────────┘
                    |         <static>          <context>      | ┌───────────┐
                    |                                          | | Channel 3 |
    ┌───────────┐   |                                          | └───────────┘
    | Builder C | ──┘                                                 ...
    └───────────┘                                                 channel-max

```

- `Builder`：队列抽象结构
  - `BuilderConfig`: 队列配置结构
  - `Builder`可以指定不同的`connection`配置进行连接，以区分业务/服务
- `Connection`：抽象的连接对象
  - `Connection`由`ConnectionManagement`管理，连接池为静态，不会因为`Builder`的释放而释放
  - `Connection Pool`中通过`get`拿取`Connection`后需要手动调用`release`归还，或者使用`action`通过传入回调函数来执行并自动归还
  - `Connection`的`publish`/`consume`使用了影子模式（当前`Connection`的`Channel`耗尽时，会自动从`Connection Pool`获取新的连接创建`Channel`）
    - 影子模式下请尽量将`Connection Pool`和`Channels Pool`的配置`wait_timeout`改小，避免过长时间的等待（等待中会出让控制权，不会阻塞）
  - 配置信息：
    - `min_connections`: 最小连接数
    - `max_connections`: 最大连接数
    - `idel_timeout`: 空闲回收时间 [s]
    - `wait_timeout`: 等待连接超时时间 [s]
- `Channel`：抽象的通道对象
  - 每一个`Connection`都具备一个`Channel`池
    - 多协程时，自动创建新的`Channel`消费，并在协程结束后自动归还/释放
    - 单协程时，复用`Channel`消费
  - 配置信息：
    - `idel_timeout`: 空闲回收时间 [s]
    - `wait_timeout`: 等待连接超时时间 [s]

## 使用

### 要求
- php >= 8.1
- webman-framework >= 2.0
- rabbitmq-server >= 3.10

### 安装

```
composer require workbunny/webman-rabbitmq
```

### 配置

#### **基础配置** `app.php`

```php
<?php declare(strict_types=1);

return [
    'enable' => true,
    // 日志 LoggerInterface | LoggerInterface::class
    'logger'   => null,
];
```

#### **连接配置** `connections.php`

```php
<?php declare(strict_types=1);

use Workbunny\WebmanRabbitMQ\Clients\AbstractClient;
use Workbunny\WebmanRabbitMQ\Connections\Connection;

return [
    'default' => [
        'connection'       => Connection::class,
        // 连接池
        'connections_pool' => [
            'min_connections'       => 1,
            'max_connections'       => 20,
            'idle_timeout'          => 60,
            'wait_timeout'          => 10
        ],
        'config' => [
            'host'               => 'rabbitmq',
            'vhost'              => '/',
            'port'               => 5672,
            'username'           => 'guest',
            'password'           => 'guest',
            'mechanism'          => 'AMQPLAIN',
            'timeout'            => 10,
            // 重启间隔
            'restart_interval'   => 5,
            'heartbeat'          => 5,
            // 通道池
            'channels_pool'      => [
                'idle_timeout'     => 60,
                'wait_timeout'     => 10
            ],
            'client_properties' => [
                'name'     => 'workbunny/webman-rabbitmq',
                'version'  => \Composer\InstalledVersions::getVersion('workbunny/webman-rabbitmq')
            ],
            // 心跳回调 callable
            'heartbeat_callback' => null,
        ]
    ]
];
```

### 命令行

- 构建：`php webman workbunny:rabbitmq-builder -h`
- 移除/关闭：`php webman workbunny:rabbitmq-remove -h`
- 列表：`php webman workbunny:rabbitmq-list -h`

### 延迟队列

**延迟队列需要为 rabbitMQ 安装 rabbitmq_delayed_message_exchange 插件**

1. 进入 rabbitMQ 的 plugins 目录下执行命令下载插件（以rabbitMQ 3.10.2举例）：
   ```shell
   wget https://github.com/rabbitmq/rabbitmq-delayed-message-exchange/releases/download/3.10.2/rabbitmq_delayed_message_exchange-3.10.2.ez
   ```
2. 执行安装命令
   ```shell
   rabbitmq-plugins enable rabbitmq_delayed_message_exchange
   ```
3. 生产
   ```PHP
   publish(new TestBuilder(), 'abc', headers: [
       'x-delay' => 10000, # 延迟10秒
   ]); # return bool
   ```
   **注：向延迟队列发布普通消息会抛出一个 WebmanRabbitMQException 异常**

#### 注意

- 不少第三方厂商不支持安装延迟队列插件
- 当不支持安装延迟队列时，可以通过优先级队列 + `REQUEUE`实现
  - `Builder`支持通过`REQUEUE`标记进行消息重入队尾
  - 通过自定义`header`中的时间标记，和逻辑判断，当满足时间条件时则执行，不满足条件则通过`REQUEUE`将数据自动推回队尾
  - 为了减少数据延迟问题，使用优先级标识将时间较近的消息优先级定义高一些，而时间较长的数据优先级定义低一些
    - 队列通常支持`0-9`的优先级，合理分配时间段和优先级的匹配关系
### 生产

**注：向延迟队列发布普通消息会抛出一个 WebmanRabbitMQPublishException 异常**

- 快捷发送
    ```php
    use function Workbunny\WebmanRabbitMQ\publish;
    use process\workbunny\rabbitmq\TestBuilder;
    
    publish(new TestBuilder(), 'abc'); # return bool
    ```

- `Builder`发送
    ```php
    use process\workbunny\rabbitmq\TestBuilder;
    use Workbunny\WebmanRabbitMQ\Connections\ConnectionInterface;
    $builder = new TestBuilder();
    $body = 'abc';
    return $builder->action(function (ConnectionInterface $connection) use ($builder, $body) {
        $config = new BuilderConfig($builder->getBuilderConfig()());
        $config->setBody($body);
        $connection->publish($config)
    });
    ```
  
- 原生发送，需要自行指定`exchange`等参数
    ```php
    use Workbunny\WebmanRabbitMQ\BuilderConfig;
    use Workbunny\WebmanRabbitMQ\Connections\ConnectionInterface;
    use Workbunny\WebmanRabbitMQ\Connections\ConnectionsManagement;
    $config = new \Workbunny\WebmanRabbitMQ\BuilderConfig();
    $config->setExchange('your_exchange');
    $config->setRoutingKey('your_routing_key');
    $config->setQueue('your_queue');
    $config->setBody('abc');
    // 其他设置参数 ...
  
    // 使用 your_connection 配置连接发送
    return ConnectionsManagement::connection(function (ConnectionInterface $connection) use ($config) {
        $connection->publish($config)
    }, 'your_connection');
    ```

## 进阶

### 1. 自定义`Connection`

- **默认使用`Workbunny\WebmanRabbitMQ\Connections\Connection`，包含影子模式**
- **创建自定义`Connection`需继承实现`ConnectionInterface`，创建自定义`Connection`会丧失影子模式的功能，除非自行实现**
- `config/plugin/workbunny/webman-rabbitmq/connections.php`修改`connection`配置项为自定义`Connection`类；

### 2. 自定义`Builder`

- 创建自定义`Builder`需继承实现`AbstractBuilder`；
  - `onWorkerStart` 消费进程启动时会触发，一般用于实现基础消费逻辑；
  - `onWorkerStop` 消费进程结束时会触发，一般用于回收资源；
  - `onWorkerReload` 消费进程重载，一般可置空；
  - `classContent` 用于配合命令行自动生成BuilderClass；
- 框架`bootstrap`中加载注册代码
  - 使用`AbstractBuilder::registerMode()`注册模式，便可使用`Command`命令行自定义`--mode={your mode}`

### 3. 自定义`Client`

- **默认使用`Workbunny\WebmanRabbitMQ\Clients\Client`，客户端继承并重写自`bunny/byunny`的`BIO`版本的`Client`**
- **自定义的`Client`需要配合自定义`Connection`进行使用，最终通过`config/plugin/workbunny/webman-rabbitmq/connections.php`配置中的`connection`进行加载**
- **通常在需要直接调用原生`RabbitMQ-Client`并拓展其功能时才需要用到自定义开发**
- **创建自定义`Client`需继承实现`Workbunny\WebmanRabbitMQ\Clients\AbstractClient`**
