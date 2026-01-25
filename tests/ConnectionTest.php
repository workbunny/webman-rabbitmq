<?php declare(strict_types=1);

namespace Workbunny\Tests;

use Bunny\ChannelStateEnum;
use Webman\Context;
use Workbunny\WebmanRabbitMQ\Channels\Channel;
use Workbunny\WebmanRabbitMQ\Connections\Connection;
use Workbunny\WebmanRabbitMQ\Connections\ConnectionsManagement;
use Workerman\Coroutine;
use Workerman\Coroutine\Pool;
use Workerman\Timer;
use Workerman\Worker;


class ConnectionTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConnectionsManagement::initialize();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ConnectionsManagement::destroy();
    }

    public function testCreateConnections()
    {
        // 连接池拉取连接
        $c1 = ConnectionsManagement::get();
        $c1->reconnect();
        $this->assertTrue($c1->isConnected());

        $c2 = ConnectionsManagement::get();
        $c2->reconnect();
        $this->assertTrue($c2->isConnected());

        // 两个连接不为同一个
        $this->assertNotSame($c1, $c2);

        $c1->disconnect();
        $this->assertFalse($c1->isConnected());

        $c2->disconnect();
        $this->assertFalse($c2->isConnected());
    }

    public function testCreateConnectionsByCoroutine()
    {
        $connections = [];
        // 模拟创建5个协程分别执行任务
        $barrier = Coroutine\Barrier::create();
        for ($i = 0; $i < 5; $i++) {
            Coroutine::create(function () use ($barrier, &$connections) {
                $connection = ConnectionsManagement::get();
                $connection->reconnect();
                $this->assertTrue($connection->isConnected());
                // 连接应为独立的
                $connections[spl_object_id($connection)] = $connection;
                // 模拟阻塞，协程切换
                Timer::sleep(5);
            });
        }
        $barrier->wait($barrier);
        $this->assertCount(5, $connections);
    }

    public function testCreateChannels()
    {
        $connection = ConnectionsManagement::get();
        $connection->reconnect();
        $this->assertTrue($connection->isConnected());

        $c1 = $connection->channel();
        $c2 = $connection->channel();

        $this->assertSame($c1, $c2);
        $this->assertEquals(ChannelStateEnum::READY, $c1->getState());
        $this->assertEquals(ChannelStateEnum::READY, $c2->getState());

        $c1->close();

        $this->assertEquals(ChannelStateEnum::CLOSED, $c1->getState());
        $this->assertEquals(ChannelStateEnum::CLOSED, $c2->getState());
    }

    public function testCreateChannelsByCoroutine()
    {
        $connection = ConnectionsManagement::get();
        $connection->reconnect();
        $this->assertTrue($connection->isConnected());

        $channels = [];
        // 模拟创建5个协程分别执行任务
        $barrier = Coroutine\Barrier::create();
        for ($i = 0; $i < 5; $i++) {
            Coroutine::create(function () use ($barrier, $connection, &$channels) {
                $c = $connection->channel();
                // channel应为独立的
                $channels[spl_object_id($c)] = $c;
                // 模拟阻塞，协程切换
                Timer::sleep(5);
            });
        }
        $barrier->wait($barrier);
        $this->assertCount(5, $channels);
    }
}