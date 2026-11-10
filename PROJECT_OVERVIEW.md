# Project Overview — workbunny/webman-rabbitmq

> A pure-PHP implementation of AMQP for webman/workerman.  
> This document is intended for AI agents to quickly understand the project structure and architecture.

## Quick Facts

| Item | Value |
|------|-------|
| Package | `workbunny/webman-rabbitmq` |
| Version | 3.x |
| PHP | ^8.1 |
| Dependencies | `bunny/bunny` ^0.5, `psr/log` ^3.0, `workerman/webman-framework` ^2.0 |
| License | MIT |
| Protocol | AMQP 0-9-1 (pure PHP, no ext required) |
| Event Loop | Workerman 5.x (Fiber/Coroutine) |

## Architecture

```
User Code
  │
  ├─ publish() / action()          ← src/helpers.php (global functions)
  │
  ├─ AbstractBuilder               ← src/Builders/AbstractBuilder.php
  │    ├─ QueueBuilder             ← src/Builders/QueueBuilder.php
  │    └─ publish() / consume()    ← Builder-level operations
  │
  ├─ ConnectionsManagement         ← src/ConnectionsManagement.php
  │    ├─ pool mode (Pool)         ← multiple connections, borrow/return
  │    └─ pool-less mode           ← single long-lived connection
  │
  ├─ Connection                    ← src/Connection/Connection.php
  │    ├─ TCP (AsyncTcpConnection) ← Workerman async TCP
  │    ├─ Channel Pool             ← Workerman Coroutine\Pool
  │    ├─ await / wakeup           ← coroutine-based AMQP request/response
  │    └─ heartbeat                ← timer-based keepalive
  │
  └─ Channel                       ← src/Connection/Channel.php
       ├─ exchangeDeclare/queueDeclare/queueBind
       ├─ publish (basicPublish)
       ├─ consume / get
       ├─ confirm / transaction
       └─ ack / nack / reject
```

## Key Concepts

### Connection Lifecycle

```
NOT_CONNECTED → CONNECTING → CONNECTED → DISCONNECTING → NOT_CONNECTED
                    ↑                          ↓
                  ERROR ←──── onClose/onError ──┘
                    ↓
              connect() (auto-reconnect)
```

- `ConnectionsManagement::connection()` calls `$instance->connect()` before every action — auto-reconnects on ERROR/NOT_CONNECTED
- `onClose`/`onError` set state=ERROR, wake up all coroutines, destroy TCP, set `tcpConnection=null`
- `onFrameReceived` handles server-initiated `connection.close` by replying `connection.close-ok` and cleaning up

### await / wakeup (Coroutine Communication)

- **await**: suspends current coroutine, registers in `awaits[channel][frameClass]` queue
- **wakeup**: resumes matching coroutine when a frame arrives
- **Channel isolation**: `awaits` is a 2D array `[channel_id][frame_class]` — each channel has its own FIFO queue, no cross-channel interference
- **wakeupAllAwaiting**: resumes ALL coroutines with an exception (used on connection break)
- **wakeupChannelAllAwaiting**: resumes all coroutines on a specific channel (used on channel.close)
- String events (e.g. `connection.connected`, `confirm.select.{id}`) use channel=0 (master channel)

### Channel Pool

- Each `Connection` has a `Pool` (Workerman Coroutine\Pool) for channels
- `channel(false, $closure)`: borrow → execute → return (closure mode, for HTTP/publish)
- `channel(false)`: borrow → store in Context → defer return (context mode, for consumer)
- `channel(true)`: return master channel (channelId=0, connection-level)
- On connection break: `channel()` finally block checks `getState() !== CONNECTED` → `channelRemove` instead of put-back

### Builder Pattern

- `AbstractBuilder`: base class with `publish()` and `consume()` methods
- `QueueBuilder`: extends AbstractBuilder, implements `onWorkerStart`/`onWorkerStop`/`onWorkerReload`
- Shadow mode: when channel pool is exhausted, recursively borrows a new connection to retry
- User creates custom Builders extending `QueueBuilder`

### Publish Flow

```
publish($builder, $body)
  → builder->action() → ConnectionsManagement::connection()
    → get connection → connect() (auto-reconnect)
    → builder->publish(connection, config)
      → connection->channel(false, $closure)
        → borrow channel from pool
        → $closure(channel):
            channel->exchangeDeclare()  → await(ExchangeDeclareOk)
            channel->queueDeclare()     → await(QueueDeclareOk)
            channel->queueBind()        → await(QueueBindOk)
            channel->publish()          → fire-and-forget (no await)
        → return channel to pool
    → release connection
```

### Consume Flow

```
builder->onWorkerStart(worker)
  → ConnectionsManagement::connection()
    → builder->consume(connection, config)
      → connection->channel(false)  (context mode)
      → channel->exchangeDeclare() / queueDeclare() / queueBind() / qos()
      → channel->consume(callback, queue)
        → await(ConsumeOk)
      → on message: callback(message, channel, connection)
        → return ACK / NACK / REQUEUE / REJECT
        → if REQUEUE: re-publish with requeue-count header, then ACK original
```

## Source Files

### Core

| File | Responsibility |
|------|---------------|
| `src/ConnectionsManagement.php` | Connection pool manager (pool/pool-less modes), `get()`/`release()`/`connection()`/`initialize()`/`destroy()` |
| `src/helpers.php` | Global functions: `publish()`, `action()`, `is_empty_dir()`, `binary_dump()` |
| `src/BuilderConfig.php` | Config object for Builder (queue/exchange/routing/QOS/etc.) |
| `src/Constants.php` | AMQP constants (exchange types, delivery modes, ack/nack/requeue) |

### Connection Layer

| File | Responsibility |
|------|---------------|
| `src/Connection/ConnectionInterface.php` | Interface: `connect()`, `disconnect()`, `channel()`, `frameSend()`, etc. |
| `src/Connection/Connection.php` | Core connection: `await()`/`wakeup()`/`wakeupAllAwaiting()`, `connect()`/`disconnect()`, `onFrameReceived()`, heartbeat |
| `src/Connection/Channel.php` | AMQP channel: `publish()`, `consume()`, `get()`, `close()`, `confirm()`, `select()`/`commit()`/`rollback()`, `onFrameReceived()` |
| `src/Connection/Traits/InitMethods.php` | TCP connection init (`onConnect`/`onMessage`/`onClose`/`onError`), channel pool init, `channel()` borrow/return logic |
| `src/Connection/Traits/ChannelsMethods.php` | AMQP protocol frames: `exchangeDeclare()`, `queueDeclare()`, `basicPublish()`, `basicConsume()`, etc. |
| `src/Connection/Traits/ConnectionMethods.php` | Connection-level frames: `connectionClose()`, `connectionCloseOk()`, `connectionHeartbeat()`, etc. |
| `src/Connection/Traits/MechanismMethods.php` | Auth mechanism handlers (PLAIN, AMQPLAIN) |
| `src/Connection/Protocol/AMQP.php` | AMQP protocol parser/serializer |

### Builders

| File | Responsibility |
|------|---------------|
| `src/Builders/AbstractBuilder.php` | Abstract builder: `publish()`, `consume()`, `action()`, shadow mode, mode registry (`queue`, `simple`) |
| `src/Builders/QueueBuilder.php` | Queue builder: `onWorkerStart()`/`onWorkerStop()`/`onWorkerReload()` |
| `src/Builders/QueueSimpleBuilder.php` | Queue builder with consumer/producer **TCP isolation**: holds one pooled connection for consuming, publishes over another one. Requires `connections_pool.enable=true` and `min_connections>=2` (validated on startup) |
| `src/Builders/Traits/BuilderConfigManagement.php` | BuilderConfig getter/setter trait |

### Commands

| File | Responsibility |
|------|---------------|
| `src/Commands/AbstractCommand.php` | Base command for webman console |
| `src/Commands/WorkbunnyWebmanRabbitMQBuilder.php` | Create builder class |
| `src/Commands/WorkbunnyWebmanRabbitMQRemove.php` | Remove builder class |
| `src/Commands/WorkbunnyWebmanRabbitMQList.php` | List builders |

### Exceptions

All in `src/Exceptions/`:
- `WebmanRabbitMQException` — base exception (extends `\RuntimeException`)
- `WebmanRabbitMQConnectException` — connection errors
- `WebmanRabbitMQChannelException` — channel state errors
- `WebmanRabbitMQChannelFulledException` — channel pool exhausted
- `WebmanRabbitMQPublishException` — publish validation errors
- `WebmanRabbitMQRequeueException` — requeue failure

## Configuration

### connections.php

```php
return [
    'default' => [
        'connection'       => Connection::class,
        'connections_pool' => [
            'enable'          => true,   // false = pool-less (single long connection)
            'min_connections' => 1,
            'max_connections' => 20,
            'idle_timeout'    => 60,
            'wait_timeout'    => 10,
        ],
        'config' => [
            'host'             => '127.0.0.1',
            'port'             => 5672,
            'vhost'            => '/',
            'username'         => 'guest',
            'password'         => 'guest',
            'mechanism'        => 'AMQPLAIN',  // or PLAIN
            'channels_pool'    => [
                'max_connections' => null,  // null = use server channel limit
                'idle_timeout'    => 60,
                'wait_timeout'    => 10,
            ],
        ],
    ],
];
```

## Usage

### Publish

```php
use function Workbunny\WebmanRabbitMQ\publish;

// Simple publish
publish($builder, 'message body');

// With routing key and headers
publish($builder, 'message', routingKey: 'key', headers: ['x-delay' => 5000]);

// Reuse connection for multiple publishes
use function Workbunny\WebmanRabbitMQ\action;
action(function ($connection) use ($builder) {
    publish($builder, 'msg1', connection: $connection);
    publish($builder, 'msg2', connection: $connection);
});
```

### Consume

```php
class MyBuilder extends QueueBuilder
{
    protected array $queueConfig = [
        'name' => 'my-queue',
        'prefetch_count' => 1,
    ];

    public function handler(Message $message, Channel $channel, ConnectionInterface $connection): string
    {
        // process $message->content
        return Constants::ACK;  // or NACK, REQUEUE, REJECT
    }
}
```

## Testing

```bash
composer tester          # run all tests
composer fmt             # format code
```

### Test Files

| File | Coverage |
|------|----------|
| `tests/BuilderConfigTest.php` | BuilderConfig defaults, getters/setters, clone independence |
| `tests/BuilderModeTest.php` | Builder mode registration |
| `tests/BuilderTest.php` | Publish, parallel publish, consume, requeue |
| `tests/ChannelAdvancedTest.php` | Transaction, confirm, get, channel close, confirm isolation |
| `tests/ConnectionTest.php` | Connection create/close, coroutine channels |
| `tests/CommandsTest.php` | Builder create/remove commands |
| `tests/ExceptionTest.php` | Exception inheritance, non-coroutine context |
| `tests/HelpersTest.php` | publish with connection, action(), header validation |
| `tests/ServerEventTest.php` | Server-initiated close, auto-reconnect, channel pool health, multiple reconnect cycles |

### Dev Environment

```bash
docker-compose up -d    # RabbitMQ + PHP container
composer tester         # run tests inside container
```

## AMQP Protocol Notes

- `connection.close` from server → client replies `connection.close-ok`, cleans up immediately (no `await`)
- `channel.close` from server → client marks channel CLOSED, wakes up channel's awaits with exception
- Heartbeat: client sends heartbeat frames at negotiated interval; failure triggers ERROR state + cleanup
- Channel isolation: each channel has independent await queue, frames are dispatched by `$frame->channel`
