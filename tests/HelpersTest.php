<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use Workbunny\Tests\TestBuilders\TestPublishBuilder;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\ConnectionsManagement;
use Workerman\Timer;

class HelpersTest extends BaseTestCase
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

    /**
     * 测试 publish() 传入已有 connection，跳过池借还
     */
    public function testPublishWithConnection(): void
    {
        $builder = new TestPublishBuilder();
        $connection = ConnectionsManagement::get();
        try {
            $res = \Workbunny\WebmanRabbitMQ\publish($builder, 'with-conn-msg', connection: $connection);
            $this->assertTrue($res > 0);
        } finally {
            ConnectionsManagement::release($connection);
        }

        Timer::sleep(2);
        $messages = $this->getQueueMessages($builder->getBuilderConfig()->getQueue(), 1, true);
        $this->assertCount(1, $messages);
        $this->assertEquals('with-conn-msg', $messages[0]['payload']);
    }

    /**
     * 测试 action() 一次借还多次 publish
     */
    public function testActionFunction(): void
    {
        $builder = new TestPublishBuilder();
        $results = \Workbunny\WebmanRabbitMQ\action(function (ConnectionInterface $connection) use ($builder) {
            $res1 = \Workbunny\WebmanRabbitMQ\publish($builder, 'action-1', connection: $connection);
            $res2 = \Workbunny\WebmanRabbitMQ\publish($builder, 'action-2', connection: $connection);
            return [$res1, $res2];
        });

        $this->assertCount(2, $results);
        $this->assertTrue($results[0] > 0);
        $this->assertTrue($results[1] > 0);

        Timer::sleep(2);
        $messages = $this->getQueueMessages($builder->getBuilderConfig()->getQueue(), 2, true);
        $this->assertCount(2, $messages);
    }

    /**
     * 测试 publish() 延迟队列 header 校验
     */
    public function testPublishDelayedHeaderValidation(): void
    {
        $builder = new TestPublishBuilder();
        // 非延迟队列带 x-delay header 应抛异常
        $this->expectException(\Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQPublishException::class);
        \Workbunny\WebmanRabbitMQ\publish($builder, 'test', headers: ['x-delay' => 5000]);
    }

    public function testIsEmptyDir(): void
    {
        $dir = sys_get_temp_dir() . '/wb_empty_test_' . uniqid();
        mkdir($dir);
        $this->assertTrue(\Workbunny\WebmanRabbitMQ\is_empty_dir($dir));

        file_put_contents($dir . '/file.txt', 'test');
        $this->assertFalse(\Workbunny\WebmanRabbitMQ\is_empty_dir($dir));
        unlink($dir . '/file.txt');

        $this->assertTrue(\Workbunny\WebmanRabbitMQ\is_empty_dir($dir, true));
        $this->assertFalse(file_exists($dir));
    }

    public function testBinaryDump(): void
    {
        $result = \Workbunny\WebmanRabbitMQ\binary_dump('Hello');
        $this->assertIsString($result);
        $this->assertStringContainsString('48', $result);
        $this->assertStringContainsString('Hello', $result);

        $this->assertEquals('', \Workbunny\WebmanRabbitMQ\binary_dump(''));

        $result2 = \Workbunny\WebmanRabbitMQ\binary_dump("\x00\x01\x02");
        $this->assertStringContainsString('00', $result2);
        $this->assertStringContainsString('01', $result2);
    }
}
