<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use Workbunny\Tests\TestBuilders\TestConsumeBuilder;
use Workbunny\Tests\TestBuilders\TestPublishBuilder;
use Workbunny\Tests\TestBuilders\TestRequeueBuilder;
use Workbunny\WebmanRabbitMQ\Connections\ConnectionsManagement;
use Workerman\Coroutine;
use Workerman\Timer;
use Workerman\Worker;
use function Workbunny\WebmanRabbitMQ\parallel_publish;

class BuilderTest extends BaseTestCase
{

    protected function tearDown(): void
    {
        parent::tearDown();
        ConnectionsManagement::destroy();
    }

//    public function testPublish()
//    {
//        $count = 5;
//        $builder = new TestPublishBuilder();
//        // 发送消息
//        for ($i = 0; $i < $count; $i++) {
//            $res = \Workbunny\WebmanRabbitMQ\publish($builder, __FUNCTION__ . '_' . $i);
//            $this->assertTrue($res);
//        }
//
//        // http-api 存在延迟
//        Timer::sleep(5);
//        // http-api获取信息
//        $res = $this->getQueueMessages($builder->getBuilderConfig()->getQueue(), $count, true);
//        // 验证
//        foreach ($res as $key => $message) {
//            $this->assertEquals(__FUNCTION__ . '_' . $key, $message['payload']);
//            $this->assertEquals($builder->getBuilderConfig()->getExchange(), $message['exchange']);
//            $this->assertEquals($builder->getBuilderConfig()->getRoutingKey(), $message['routing_key']);
//        }
//    }

//    public function testParallelPublish()
//    {
//        $count = 5;
//        $functionName = __FUNCTION__;
//        $builder = new TestPublishBuilder();
//        $multiData = [];
//        for ($i = 0; $i < $count; $i++) {
//            $multiData[] = [$functionName . '_' . $i, null, null];
//        }
//        $res = parallel_publish($builder, $multiData);
//        /**
//         * @var bool $status
//         * @var null|string $msg
//         */
//        foreach ($res as [$status, $msg]) {
//            $this->assertTrue($status);
//            $this->assertNull($msg);
//        }
//
//        // http-api 存在延迟
//        Timer::sleep(5);
//        // http-api获取信息
//        $res = $this->getQueueMessages($builder->getBuilderConfig()->getQueue(), $count, true);
//        // 验证
//        $payloads = $exchanges = $routingKeys = [];
//        foreach ($res as $key => $message) {
//            $payloads[] = $message['payload'];
//            $exchanges[] = $message['exchange'];
//            $routingKeys[] = $message['routing_key'];
//        }
//        $this->assertContains();
//    }

    public function testManualParallelPublish()
    {
        $count = 2;
        $functionName = __FUNCTION__;
        $builder = new TestPublishBuilder();
        $p = new Coroutine\Parallel();
        try {
            for ($i = 0; $i < $count; $i++) {
                $p->add(function () use ($i, $builder, $functionName) {
                    $res = \Workbunny\WebmanRabbitMQ\publish($builder, __FUNCTION__ . '_' . $i);
                    $this->assertTrue($res);
                });
            }
            $p->wait();

            // http-api 存在延迟
            Timer::sleep(5);
            // http-api获取信息
            $res = $this->getQueueMessages($builder->getBuilderConfig()->getQueue(), $count, true);
            // 验证
            foreach ($res as $key => $message) {
                $this->assertEquals($functionName . '_' . $key, $message['payload']);
                $this->assertEquals($builder->getBuilderConfig()->getExchange(), $message['exchange']);
                $this->assertEquals($builder->getBuilderConfig()->getRoutingKey(), $message['routing_key']);
            }
        } finally {
            ConnectionsManagement::release($connection);
        }
    }

//        public function testConsume()
//        {
//            $log = __DIR__ . '/test-consume-builder.log';
//            $expected = [];
//            try {
//
//                $count = 5;
//                $builder = new TestConsumeBuilder();
//                // 模拟进程启动 消费
//                $builder->onWorkerStart(new Worker());
//                // 发送消息
//                for ($i = 0; $i < $count; $i ++) {
//                    $res = \Workbunny\WebmanRabbitMQ\publish($builder, $payload = __FUNCTION__ . '_' . $i, close: $i % 2 === 0);
//                    $this->assertTrue($res);
//                    $expected[$i] = [
//                        'exchange' => $builder->getBuilderConfig()->getExchange(),
//                        'routing_key' => $builder->getBuilderConfig()->getRoutingKey(),
//                        'payload' => $payload
//                    ];
//                }
//
//                // 出让协程，等待消费完毕
//                Timer::sleep(10);
//                // 验证
//                $this->assertTrue(file_exists($log));
//
//                $actual = [];
//                $content = new \SplFileObject($log);
//                foreach ($content as $message) {
//                    if ($message) {
//                        $this->assertTrue(is_string($message));
//                        $this->assertJson($message = trim($message));
//                        $actual[] = json_decode($message, true);
//                    }
//                }
//                $this->assertEquals($expected, $actual);
//
//            } finally {
//                if (file_exists($log)) {
//                    unlink($log);
//                }
//            }
//        }
//
//        public function testRequeue()
//        {
//            $log = __DIR__ . '/test-requeue-builder.log';
//            try {
//                $builder = new TestRequeueBuilder();
//                $builder->setLogFile($log);
//                $res = \Workbunny\WebmanRabbitMQ\publish($builder, __FUNCTION__ . '_requeue');
//                $this->assertTrue($res);
//                // 模拟消费
//                $builder->onWorkerStart(new Worker());
//                Timer::sleep(10);
//
//                $actual = [];
//                $content = new \SplFileObject($log);
//                foreach ($content as $message) {
//                    if ($message) {
//                        $this->assertTrue(is_string($message));
//                        $this->assertJson($message = trim($message));
//                        $actual[] = $message = json_decode($message, true);
//                        $this->assertArrayHasKey('workbunny-requeue-count', $message);
//                        $this->assertEquals(5, $message['workbunny-requeue-count']);
//                    }
//                }
//                $this->assertNotEmpty($actual);
//            } finally {
//                if (file_exists($log)) {
//                    unlink($log);
//                }
//            }
//        }
}
