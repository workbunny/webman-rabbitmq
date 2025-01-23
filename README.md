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

- 此文档为2.0，[1.0文档](https://github.com/workbunny/webman-rabbitmq/blob/1.x/README.md)
- 1.0已停止维护

## 简介

RabbitMQ的webman客户端插件；

1. 支持5种消费模式：简单队列、workQueue、routing、pub/sub、exchange；
2. 支持延迟队列（rabbitMQ须安装插件）；
3. 异步无阻塞消费、异步无阻塞生产、同步阻塞生产；

### 概念

#### 1. Builder

- Builder为队列的抽象结构，每个Builder都包含一个BuilderConfig配置结构对象
- 当前进程的所有Builder公用一个对象池，对象池可用于减少连接的创建和销毁，提升性能
- 当前进程的所有Builder公用一个Connection连接对象池：
  - 当reuse_connection=false时，Builder之间使用各自的Connection连接对象
  - 当reuse_connection=true时，不同Builder复用同一个Connection连接对象

#### 2. Connection

- Connection是抽象的连接对象，每个Connection会创建两个TCP连接：
  - `getAsyncClient()`获取AsyncClient 异步客户端连接
  - `getSyncClient()`获取SyncClient 同步客户端连接
- 一个Connection对象在RabbitMQ-server中等于两个连接
- 所有Builder的Connection连接对象在Builder的Connection池中进行统一管理
- 当reuse_connection=true时，Connection对象在池中的key为空字符串

#### 3. Channel

- Channel是基于RabbitMQ-server的连接对象的子连接
- Channel的上限默认根据RabbitMQ-server的channel limit配置
- Channel的生命周期与Connection一致，在Connection存续期间，Channel不会被销毁
- Channel池可以启用/关闭复用模式中：
  - 当reuse_channel=true时，连接会使用Channel池中闲置通道进行发送，如当前不存在闲置通道，则创建新的通道；
  - 当reuse_channel=false时，连接每次都会创建新的通道，建议在生产完成后调用连接关闭
- AsyncClient和SyncClient互相不共用TCP连接，所以Channel池也不公用
- 可以通过`getChannels`方法获取Channel对象池，自行管理释放

## 使用

### 要求
- php >= 8.0
- webman-framework >= 1.5
- rabbitmq-server >= 3.10

### 安装

```
composer require workbunny/webman-rabbitmq
```

### 配置

#### app.php

```php
<?php
return [
    'enable' => true,
    // 复用连接
    'reuse_connection'   => false,
    // 复用通道
    'reuse_channel'      => false,
    
    // 以下内容2.2开始已弃用，请使用config/rabbitmq.php配置
    'host'               => 'rabbitmq',
    'vhost'              => '/',
    'port'               => 5672,
    'username'           => 'guest',
    'password'           => 'guest',
    'mechanism'          => 'AMQPLAIN',
    'timeout'            => 10,
    // 重启间隔
    'restart_interval'   => 0,
    // 心跳间隔
    'heartbeat'          => 50,
    // 心跳回调
    'heartbeat_callback' => function(){
    },
    // 错误回调
    'error_callback'     => function(Throwable $throwable){
    },
    
    // AMQPS 如需使用AMQPS请取消注释
//    'ssl'                => [
//        'cafile'      => 'ca.pem',
//        'local_cert'  => 'client.cert',
//        'local_pk'    => 'client.key',
//    ],
];
```

#### rabbitmq.php

**`Builder`中增加了`protected ?string $connection = null;`属性，用于指定使用`config/rabbitmq.php`中定义的连接**

```php
<?php
return [
    'connections' => [
        'rabbitmq' => [
            'host'               => 'rabbitmq',
            'vhost'              => '/',
            'port'               => 5672,
            'username'           => 'guest',
            'password'           => 'guest',
            'mechanism'          => 'AMQPLAIN',
            'timeout'            => 10,
            // 重启间隔
            'restart_interval'   => 0,
            // 心跳间隔
            'heartbeat'          => 50,
            // 心跳回调
            'heartbeat_callback' => function(){
            },
            // 错误回调
            'error_callback'     => function(Throwable $throwable){
            },
//            // AMQPS 如需使用AMQPS请取消注释
//            'ssl' => [
//                'cafile' => 'ca.pem',
//                'local_cert' => 'client.cert',
//                'local_pk' => 'client.key',
//            ],
        ]
    ]
];
```

### QueueBuilder / CoQueueBuilder

- QueueBuilder: 原队列Builder，采用event-loop实现异步消费
- CoQueueBuilder: 协程队列Builder，采用协程实现异步消费，需要`workerman/rabbitmq 2.0`
- 两种Builder均可实现官网的5种消费模式，使用方式一致，可平滑切换

#### 命令行

- 创建
```shell
# 创建一个拥有单进程消费者的QueueBuilder
./webman workbunny:rabbitmq-builder test --mode=queue
# 创建一个拥有4进程消费者的QueueBuilder
./webman workbunny:rabbitmq-builder test 4 --mode=queue

# 创建一个拥有单进程消费者的延迟QueueBuilder
./webman workbunny:rabbitmq-builder test --delayed --mode=queue
# 创建一个拥有4进程消费者的延迟QueueBuilder
./webman workbunny:rabbitmq-builder test 4 --delayed --mode=queue


# 在 process/workbunny/rabbitmq 目录下创建 TestBuilder.php
./webman workbunny:rabbitmq-builder test --mode=queue
# 在 process/workbunny/rabbitmq/project 目录下创建 TestBuilder.php
./webman workbunny:rabbitmq-builder project/test --mode=queue
# 在 process/workbunny/rabbitmq/project 目录下创建 TestAllBuilder.php
./webman workbunny:rabbitmq-builder project/testAll --mode=queue
# 延迟同理
```

**注：`CoQueueBuilder`请使用`--mode=co-queue`**

- 移除

移除包含了类文件的移除和配置的移除

```shell
# 移除Builder
./webman workbunny:rabbitmq-remove test
# 移除延迟Builder
./webman workbunny:rabbitmq-remove test --delayed

# 二级菜单同理
```

- 关闭

关闭仅对配置进行移除

```shell
# 关闭Builder
./webman workbunny:rabbitmq-remove test --close
# 关闭延迟Builder
./webman workbunny:rabbitmq-remove test --close --delayed

# 二级菜单同理
```

### 注意

- **创建的Builder类可以手动修改调整**
- **为Builder添加进process.php的配置可以手动修改**
- **延迟队列需要为 rabbitMQ 安装 rabbitmq_delayed_message_exchange 插件**
  1. 进入 rabbitMQ 的 plugins 目录下执行命令下载插件（以rabbitMQ 3.8.x举例）：
    ```shell
    wget https://github.com/rabbitmq/rabbitmq-delayed-message-exchange/releases/download/3.8.17/rabbitmq_delayed_message_exchange-3.8.17.8f537ac.ez
    ```
  2. 执行安装命令
    ```shell
    rabbitmq-plugins enable rabbitmq_delayed_message_exchange
    ```

### 查看Builder

```shell
./webman workbunny:rabbitmq-list
```

**注：当 Builder 未启动时，handler 与 count 显示为 --**

```shell
+----------+-------------------------------------------------------------------------+-------------------------------------------------+-------+-------+
| name     | file                                                                    | handler                                         | count | mode  |
+----------+-------------------------------------------------------------------------+-------------------------------------------------+-------+-------+
| test     | /var/www/your-project/process/workbunny/rabbitmq/TestBuilder.php        | process\workbunny\rabbitmq\TestBuilder          | 1     | queue |
| test -d  | /var/www/your-project/process/workbunny/rabbitmq/TestBuilderDelayed.php | process\workbunny\rabbitmq\TestBuilderDelayed   | 1     | group |
+----------+-------------------------------------------------------------------------+-------------------------------------------------+-------+-------+
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

sync_publish(TestBuilder::instance(), 'abc', headers: [
	'x-delay' => 10000, # 延迟10秒
]); # return bool
```

#### 2. 异步发布消息

**该方法不会阻塞等待，立即返回 [React\Promise](https://github.com/reactphp/promise)，
可以利用 [React\Promise](https://github.com/reactphp/promise) 进行 wait；
也可以纯异步不等待，[React\Promise 项目地址](https://github.com/reactphp/promise)；**
- 发布普通消息

**注：向延迟队列发布普通消息会抛出一个 WebmanRabbitMQException 异常**

**注：`CoQueueBuilder`不会返回`Promise`**

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

async_publish(TestBuilder::instance(), 'abc', headers: [
	'x-delay' => 10000, # 延迟10秒
]); # return PromiseInterface|bool
```
## 进阶

### 1. 自定义Builder

- 创建自定义Builder需继承实现AbstractBuilder；
  - onWorkerStart 消费进程启动时会触发，一般用于实现基础消费逻辑；
  - onWorkerStop 消费进程结束时会触发，一般用于回收资源；
  - onWorkerReload 消费进程重载，一般可置空；
  - classContent 用于配合命令行自动生成BuilderClass；

```php
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
```

## 说明
- **生产可用，欢迎 [issue](https://github.com/workbunny/webman-rabbitmq/issues) 和 PR**；
- **Message** 可以理解为队列、交换机的配置信息；
- 继承实现 **AbstractMessage** 可以自定义Message；
- **Builder** 可通过 **Builder->setMessage()** 可设置自定义配置；
- 可使用 **SyncClient** 或 **AsyncClient** 自行实现一些自定义消费/自定义生产的功能；
