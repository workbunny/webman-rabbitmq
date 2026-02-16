<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-rabbitmq</p>**

**<p align="center">🐇 A PHP implementation of RabbitMQ Client for webman plugin. 🐇</p>**

# A PHP implementation of RabbitMQ Client for webman plugin


[![Latest Stable Version](https://badgen.net/packagist/v/workbunny/webman-rabbitmq/latest)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![Total Downloads](https://badgen.net/packagist/dt/workbunny/webman-rabbitmq)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![License](https://badgen.net/packagist/license/workbunny/webman-rabbitmq)](https://packagist.org/packages/workbunny/webman-rabbitmq) [![PHP Version Require](https://badgen.net/packagist/php/workbunny/webman-rabbitmq)](https://packagist.org/packages/workbunny/webman-rabbitmq)

### 说明

- 此文档为3.0
- 2.0 LTS [2.0文档](https://github.com/workbunny/webman-rabbitmq/blob/2.x/README.md)

## 简介

适配`Workerman`/`webman`的`AMQP`组件包

- 支持基于`AMQP`协议工具实现`AMQP-Server`
- 支持5种消费模式：简单队列、workQueue、routing、pub/sub、exchange；
- 支持延迟队列（rabbitMQ须安装插件）；
- 支持连接池，支持通道池，`Builder`支持影子模式（并发补偿）；
- 3.0与之前版本相比，更符合`AMQO`协议约定，更合理的架构设计和使用逻辑
  	- 使用`ConnectionManagement`多连接管理器管理`Connection`（`Client`），合理复用机制及并发使用能力
  	- 使用`Channel-Pool`管理`Channel`，合理的复用和并发机制
  	- 提供`AMQP`协议包，可供开发者自定义实现`AMQP-Client`或`AMQP-Server`，并提供`AMQP-Frame`协议帧工具
 
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
- `Builder`：队列消费者、生产者的抽象结构，类似`ORM`的`Model`
  - `BuilderConfig`: 队列配置结构
  - `Builder`可以指定不同的`connection`配置进行连接，以区分业务/服务
  - `Builder`的`publish`/`consume`使用了影子模式（当前`Connection`的`Channel`耗尽时，会自动从`Connection Pool`获取新的连接创建`Channel`）
      - 影子模式下请尽量将`Connection Pool`和`Channels Pool`的配置`wait_timeout`改小，避免过长时间的等待（等待中会出让控制权，不会阻塞）
- `Connection`：基于`AsyncTcpConnection`封装的`AMQP-client`
  - `Connection`由`ConnectionManagement`管理，连接池为静态，不会因为`Builder`的释放而释放
  - `Connection Pool`中通过`get`拿取`Connection`后需要手动调用`release`归还，或者使用`action`通过传入回调函数来执行并自动归还
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
- `AMQP`: `workerman`支持的协议封装

**[详细文档](https://workbunny.github.io/webman-rabbitmq/)**

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
    use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
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
    use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
    use Workbunny\WebmanRabbitMQ\ConnectionsManagement;
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
