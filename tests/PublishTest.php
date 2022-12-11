<?php
declare(strict_types=1);

namespace Tests;

use Bunny\Channel;
use Bunny\Exception\ClientException;
use Workbunny\WebmanRabbitMQ\Connection;
use Workbunny\WebmanRabbitMQ\SyncConnection;
use Workerman\Events\Select;
use Workerman\Worker;
use function Workbunny\WebmanRabbitMQ\async_publish;
use function Workbunny\WebmanRabbitMQ\sync_publish;

class PublishTest extends BaseTest
{
    public function testFastBuilderSyncPublishConnectFailed()
    {
        $test = TestBuilder::instance();
        $test->syncConnection()->_setInitCallback(function (?\Throwable $throwable, SyncConnection $connection) use($test){
            $this->assertNull($throwable);
            $this->assertNull($connection->_getChannel(false));

            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getQueue());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getRoutingKey());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getConsumerTag());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getExchange());
            $this->assertEquals('direct', $test->getMessage()->getExchangeType());
            $this->assertEquals(0, $test->getMessage()->getPrefetchCount());
            $this->assertEquals(1, $test->getMessage()->getPrefetchSize());
            $this->assertEquals(false, $test->getMessage()->isAutoDelete());
            $this->assertEquals(true, $test->getMessage()->isDurable());
            $this->assertEquals(false, $test->getMessage()->isGlobal());
            $this->assertEquals(false, $test->getMessage()->isPassive());
            $this->assertEquals(false, $test->getMessage()->isExclusive());
            $this->assertEquals(false, $test->getMessage()->isNowait());
            $this->assertEquals(false, $test->getMessage()->isImmediate());
            $this->assertEquals(false, $test->getMessage()->isNoLocal());
            $this->assertEquals(false, $test->getMessage()->isInternal());
            $this->assertEquals(false, $test->getMessage()->isMandatory());
            $this->assertEquals(false, $test->getMessage()->isNoAck());
            $this->assertEquals('test', $test->getMessage()->getBody());
            $this->assertEquals([], $test->getMessage()->getArguments());
            $this->assertEquals([
                "content_type" => "text/plain",
                "delivery_mode" => 2
            ], $test->getMessage()->getHeaders());
        });
        $test->syncConnection()->_setErrorCallback(function (\Throwable $throwable, SyncConnection $connection) use($test){
            $this->assertEquals(true, $throwable instanceof ClientException);
            $this->assertNull($connection->_getChannel(false));

            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getQueue());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getRoutingKey());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getConsumerTag());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getExchange());
            $this->assertEquals('direct', $test->getMessage()->getExchangeType());
            $this->assertEquals(0, $test->getMessage()->getPrefetchCount());
            $this->assertEquals(1, $test->getMessage()->getPrefetchSize());
            $this->assertEquals(false, $test->getMessage()->isAutoDelete());
            $this->assertEquals(true, $test->getMessage()->isDurable());
            $this->assertEquals(false, $test->getMessage()->isGlobal());
            $this->assertEquals(false, $test->getMessage()->isPassive());
            $this->assertEquals(false, $test->getMessage()->isExclusive());
            $this->assertEquals(false, $test->getMessage()->isNowait());
            $this->assertEquals(false, $test->getMessage()->isImmediate());
            $this->assertEquals(false, $test->getMessage()->isNoLocal());
            $this->assertEquals(false, $test->getMessage()->isInternal());
            $this->assertEquals(false, $test->getMessage()->isMandatory());
            $this->assertEquals(false, $test->getMessage()->isNoAck());
            $this->assertEquals('test', $test->getMessage()->getBody());
            $this->assertEquals([], $test->getMessage()->getArguments());
            $this->assertEquals([
                "content_type" => "text/plain",
                "delivery_mode" => 2
            ], $test->getMessage()->getHeaders());
        });
        $test->syncConnection()->_setFinallyCallback(function (?\Throwable $throwable, SyncConnection $connection){
            $this->assertEquals(true, $throwable instanceof ClientException);
            $this->assertNull($connection->_getChannel(false));
        });
        sync_publish(TestBuilder::instance(), 'test', null, true);
    }

    public function testFastBuilderAsyncPublishConnectFailed()
    {
        Worker::$globalEvent = new Select();
        $test = TestBuilder::instance();
        $test->connection()->_setErrorCallback(function (\Throwable $throwable, Connection $connection) use($test){
            $this->assertEquals(true, $throwable instanceof ClientException);
            $this->assertNull($connection->_getChannel());

            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getQueue());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getRoutingKey());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getConsumerTag());
            $this->assertEquals('Tests.TestBuilder', $test->getMessage()->getExchange());
            $this->assertEquals('direct', $test->getMessage()->getExchangeType());
            $this->assertEquals(0, $test->getMessage()->getPrefetchCount());
            $this->assertEquals(1, $test->getMessage()->getPrefetchSize());
            $this->assertEquals(false, $test->getMessage()->isAutoDelete());
            $this->assertEquals(true, $test->getMessage()->isDurable());
            $this->assertEquals(false, $test->getMessage()->isGlobal());
            $this->assertEquals(false, $test->getMessage()->isPassive());
            $this->assertEquals(false, $test->getMessage()->isExclusive());
            $this->assertEquals(false, $test->getMessage()->isNowait());
            $this->assertEquals(false, $test->getMessage()->isImmediate());
            $this->assertEquals(false, $test->getMessage()->isNoLocal());
            $this->assertEquals(false, $test->getMessage()->isInternal());
            $this->assertEquals(false, $test->getMessage()->isMandatory());
            $this->assertEquals(false, $test->getMessage()->isNoAck());
            $this->assertEquals('test', $test->getMessage()->getBody());
            $this->assertEquals([], $test->getMessage()->getArguments());
            $this->assertEquals([
                "content_type" => "text/plain",
                "delivery_mode" => 2
            ], $test->getMessage()->getHeaders());
        });

        async_publish(TestBuilder::instance(), 'test', null, true);
    }
}