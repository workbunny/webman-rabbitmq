<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use Bunny\ChannelStateEnum;
use Bunny\ClientStateEnum;
use Bunny\Constants;
use Workbunny\WebmanRabbitMQ\Connection\Connection;
use Workbunny\WebmanRabbitMQ\ConnectionsManagement;
use Workerman\Coroutine;
use Workerman\Timer;

class ConnectionTest extends BaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ConnectionsManagement::initialize();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ConnectionsManagement::destroy();
    }

    public function testCreateConnections()
    {
        try {
            // 连接池拉取连接
            $c1 = ConnectionsManagement::get();
            $c2 = ConnectionsManagement::get();

            // 两个连接不为同一个
            $this->assertNotSame($c1, $c2);
            // 初始化连接
            $this->assertTrue($c1->getState() === ClientStateEnum::CONNECTED);
            $this->assertTrue($c2->getState() === ClientStateEnum::CONNECTED);
            // 重复连接
            $c1->connect();
            $c2->connect();
            $this->assertTrue($c1->getState() === ClientStateEnum::CONNECTED);
            $this->assertTrue($c2->getState() === ClientStateEnum::CONNECTED);

            // 关闭连接
            $c1->disconnect();
            $c2->disconnect();

            $this->assertTrue($c1->getState() === ClientStateEnum::NOT_CONNECTED);
            $this->assertTrue($c2->getState() === ClientStateEnum::NOT_CONNECTED);

            // 再次连接
            $c1->connect();
            $c2->connect();
            $this->assertTrue($c1->getState() === ClientStateEnum::CONNECTED);
            $this->assertTrue($c2->getState() === ClientStateEnum::CONNECTED);
        } finally {
            ConnectionsManagement::release($c1 ?? null);
            ConnectionsManagement::release($c2 ?? null);
        }
    }

    public function testCreateConnectionsByCoroutine()
    {
        $count = 2;
        $connections = [];
        // 模拟创建x个协程分别执行任务
        $p = new Coroutine\Parallel();
        for ($i = 0; $i < $count; $i++) {
            $p->add(function () use (&$connections) {
                try {
                    $connection = ConnectionsManagement::get();
                    $connection->connect();
                    $this->assertTrue($connection->getState() === ClientStateEnum::CONNECTED);
                    // 连接应为独立的
                    $connections[spl_object_id($connection)] = $connection;
                    // 模拟阻塞，协程切换
                    Timer::sleep(5);
                } finally {
                    ConnectionsManagement::release($connection ?? null);
                }
            });
        }

        $p->wait();

        $this->assertCount($count, $connections);
    }

    public function testCreateChannels()
    {
        try {
            /** @var Connection $connection */
            $connection = ConnectionsManagement::get();
            $connection->connect();
            $this->assertTrue($connection->getState() === ClientStateEnum::CONNECTED);
            // master channel
            $this->assertCount(1, $connection->channelUsed());

            $c1 = $connection->channel();
            $c2 = $connection->channel();
            // not master channel
            $this->assertTrue($c1->id() !== Constants::CONNECTION_CHANNEL);
            $this->assertEquals(ChannelStateEnum::READY, $c1->getState());
            // same channel
            $this->assertSame($c1, $c2);
            $this->assertTrue($c1->id() === $c2->id());

            $c1->close();
            $this->assertEquals(ChannelStateEnum::CLOSED, $c1->getState());
        } finally {
            ConnectionsManagement::release($connection ?? null);
        }
    }

    public function testCreateChannelsByCoroutine()
    {
        try {
            /** @var Connection $connection */
            $connection = ConnectionsManagement::get();
            $connection->connect();
            $this->assertTrue($connection->getState() === ClientStateEnum::CONNECTED);

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
        } finally {
            ConnectionsManagement::release($connection ?? null);
        }
    }
}
