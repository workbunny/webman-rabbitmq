<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use Bunny\ChannelModeEnum;
use Bunny\ChannelStateEnum;
use Workbunny\WebmanRabbitMQ\Connection\Channel;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\ConnectionsManagement;
use Workerman\Coroutine;
use Workerman\Timer;

class ChannelAdvancedTest extends BaseTestCase
{
    private const TEST_QUEUES = [
        'test-tx-commit',
        'test-tx-rollback',
        'test-confirm',
        'test-confirm-iso-0',
        'test-confirm-iso-1',
        'test-confirm-iso-2',
        'test-get',
    ];

    private static bool $cleaned = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ConnectionsManagement::initialize();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // 只在首次测试前清理一次残留队列
        if (!self::$cleaned) {
            foreach (self::TEST_QUEUES as $queue) {
                @$this->request(
                    '/api/queues/' . urlencode($this->vhost) . '/' . urlencode($queue),
                    'DELETE'
                );
            }
            self::$cleaned = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ConnectionsManagement::destroy();
    }

    public function testTransactionCommit(): void
    {
        ConnectionsManagement::connection(function (ConnectionInterface $connection) {
            $connection->channel(false, function (Channel $channel) {
                $channel->queueDeclare($channel->id(), 'test-tx-commit');
                $channel->select();
                $this->assertEquals(ChannelModeEnum::TRANSACTIONAL, $channel->getMode());
                $channel->publish('tx-msg', [], '', 'test-tx-commit');
                $channel->commit();
                // 关闭 channel，服务器端事务模式不可切换，必须关闭重开
                $channel->close();
            });
        });

        Timer::sleep(2);
        $messages = $this->getQueueMessages('test-tx-commit', 1, true);
        $this->assertCount(1, $messages);
        $this->assertEquals('tx-msg', $messages[0]['payload']);
    }

    public function testTransactionRollback(): void
    {
        ConnectionsManagement::connection(function (ConnectionInterface $connection) {
            $connection->channel(false, function (Channel $channel) {
                $channel->queueDeclare($channel->id(), 'test-tx-rollback');
                $channel->select();
                $channel->publish('rollback-msg', [], '', 'test-tx-rollback');
                $channel->rollback();
                $this->assertEquals(ChannelModeEnum::TRANSACTIONAL, $channel->getMode());
                // 关闭 channel，服务器端事务模式不可切换，必须关闭重开
                $channel->close();
            });
        });

        Timer::sleep(2);
        $messages = $this->getQueueMessages('test-tx-rollback', 1, true);
        $this->assertEmpty($messages);
    }

    public function testTransactionRejectWhenNotTransactional(): void
    {
        $this->expectException(\Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException::class);
        ConnectionsManagement::connection(function (ConnectionInterface $connection) {
            $connection->channel(false, function (Channel $channel) {
                $channel->commit();
            });
        });
    }

    /**
     * 回归测试问题4：confirm() 应 await MethodConfirmSelectOkFrame 而非 MethodTxSelectOkFrame
     */
    public function testConfirm(): void
    {
        $frame = ConnectionsManagement::connection(function (ConnectionInterface $connection) {
            return $connection->channel(false, function (Channel $channel) {
                $channel->queueDeclare($channel->id(), 'test-confirm');
                $frame = $channel->confirm(function () use ($channel) {
                    $channel->publish('confirm-msg', [], '', 'test-confirm');
                });
                // 关闭 channel，服务器端 confirm 模式不可切换，必须关闭重开
                $channel->close();
                return $frame;
            });
        });

        $this->assertNotNull($frame);

        Timer::sleep(2);
        $messages = $this->getQueueMessages('test-confirm', 1, true);
        $this->assertCount(1, $messages);
        $this->assertEquals('confirm-msg', $messages[0]['payload']);
    }

    /**
     * 回归测试问题A：confirm.select.{channelId} 隔离，多 channel 并发 confirm 不串帧
     */
    public function testConfirmChannelIsolation(): void
    {
        $results = [];
        $parallel = new Coroutine\Parallel();
        for ($i = 0; $i < 3; $i++) {
            $parallel->add(function () use (&$results, $i) {
                $results[$i] = ConnectionsManagement::connection(function (ConnectionInterface $connection) use ($i) {
                    return $connection->channel(false, function (Channel $channel) use ($i) {
                        $channel->queueDeclare($channel->id(), "test-confirm-iso-{$i}");
                        $frame = $channel->confirm(function () use ($channel, $i) {
                            $channel->publish("msg-{$i}", [], '', "test-confirm-iso-{$i}");
                        });
                        $channel->close();
                        return $frame;
                    });
                });
            });
        }
        $parallel->wait();

        $this->assertCount(3, $results);
        foreach ($results as $frame) {
            $this->assertNotNull($frame);
        }

        Timer::sleep(2);
        for ($i = 0; $i < 3; $i++) {
            $messages = $this->getQueueMessages("test-confirm-iso-{$i}", 1, true);
            $this->assertCount(1, $messages);
            $this->assertEquals("msg-{$i}", $messages[0]['payload']);
        }
    }

    public function testGet(): void
    {
        ConnectionsManagement::connection(function (ConnectionInterface $connection) {
            $connection->channel(false, function (Channel $channel) {
                $channel->queueDeclare($channel->id(), 'test-get');
                $channel->publish('get-msg', [], '', 'test-get');
            });
        });

        Timer::sleep(2);

        $message = ConnectionsManagement::connection(function (ConnectionInterface $connection) {
            return $connection->channel(false, function (Channel $channel) {
                $result = null;
                $channel->get(function ($msg) use (&$result) {
                    $result = $msg;
                }, 'test-get');
                return $result;
            });
        });

        $this->assertNotNull($message);
        $this->assertEquals('get-msg', $message->content);
    }

    public function testChannelClose(): void
    {
        $connection = ConnectionsManagement::get();
        try {
            $channel = $connection->channel();
            $this->assertEquals(ChannelStateEnum::READY, $channel->getState());
            $channel->close();
            $this->assertEquals(ChannelStateEnum::CLOSED, $channel->getState());
            // 重复 close 不报错
            $result = $channel->close();
            $this->assertNull($result);
        } finally {
            ConnectionsManagement::release($connection);
        }
    }
}
