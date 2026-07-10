<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use Workbunny\WebmanRabbitMQ\BuilderConfig;
use Workbunny\WebmanRabbitMQ\Constants;

class BuilderConfigTest extends BaseTestCase
{
    public function testDefaultValues(): void
    {
        $config = new BuilderConfig();
        $this->assertEquals('', $config->getQueue());
        $this->assertFalse($config->isPassive());
        $this->assertTrue($config->isDurable());
        $this->assertFalse($config->isExclusive());
        $this->assertFalse($config->isAutoDelete());
        $this->assertFalse($config->isNowait());
        $this->assertEquals([], $config->getArguments());
        $this->assertEquals(0, $config->getPrefetchSize());
        $this->assertEquals(0, $config->getPrefetchCount());
        $this->assertFalse($config->isGlobal());
        $this->assertEquals('', $config->getBody());
        $this->assertEquals([
            'content-type'  => 'text/plain',
            'delivery-mode' => Constants::DELIVERY_MODE_PERSISTENT,
        ], $config->getHeaders());
        $this->assertEquals('', $config->getExchange());
        $this->assertEquals(Constants::DIRECT, $config->getExchangeType());
        $this->assertEquals('', $config->getRoutingKey());
        $this->assertFalse($config->isInternal());
        $this->assertFalse($config->isMandatory());
        $this->assertFalse($config->isImmediate());
        $this->assertEquals('', $config->getConsumerTag());
        $this->assertFalse($config->isNoLocal());
        $this->assertFalse($config->isNoAck());
        $this->assertTrue($config->isIsRequeue());
    }

    public function testGetterSetter(): void
    {
        $config = new BuilderConfig();
        $config->setQueue('test-queue');
        $config->setPassive(true);
        $config->setDurable(false);
        $config->setExclusive(true);
        $config->setAutoDelete(true);
        $config->setNowait(true);
        $config->setArguments(['x-max-priority' => 10]);
        $config->setPrefetchSize(1024);
        $config->setPrefetchCount(5);
        $config->setGlobal(true);
        $config->setBody('hello');
        $config->setHeaders(['content-type' => 'application/json']);
        $config->setExchange('test-exchange');
        $config->setExchangeType(Constants::FANOUT);
        $config->setRoutingKey('test-key');
        $config->setInternal(true);
        $config->setMandatory(true);
        $config->setImmediate(true);
        $config->setConsumerTag('test-tag');
        $config->setNoLocal(true);
        $config->setNoAck(true);
        $config->setIsRequeue(false);
        $callback = fn() => Constants::ACK;
        $config->setCallback($callback);

        $this->assertEquals('test-queue', $config->getQueue());
        $this->assertTrue($config->isPassive());
        $this->assertFalse($config->isDurable());
        $this->assertTrue($config->isExclusive());
        $this->assertTrue($config->isAutoDelete());
        $this->assertTrue($config->isNowait());
        $this->assertEquals(['x-max-priority' => 10], $config->getArguments());
        $this->assertEquals(1024, $config->getPrefetchSize());
        $this->assertEquals(5, $config->getPrefetchCount());
        $this->assertTrue($config->isGlobal());
        $this->assertEquals('hello', $config->getBody());
        $this->assertEquals(['content-type' => 'application/json'], $config->getHeaders());
        $this->assertEquals('test-exchange', $config->getExchange());
        $this->assertEquals(Constants::FANOUT, $config->getExchangeType());
        $this->assertEquals('test-key', $config->getRoutingKey());
        $this->assertTrue($config->isInternal());
        $this->assertTrue($config->isMandatory());
        $this->assertTrue($config->isImmediate());
        $this->assertEquals('test-tag', $config->getConsumerTag());
        $this->assertTrue($config->isNoLocal());
        $this->assertTrue($config->isNoAck());
        $this->assertFalse($config->isIsRequeue());
        $this->assertSame($callback, $config->getCallback());
    }

    /**
     * 回归测试优化1：clone 替代反射，确保浅拷贝独立性
     */
    public function testCloneIsIndependent(): void
    {
        $config = new BuilderConfig();
        $config->setBody('original');
        $config->setHeaders(['key' => 'value']);
        $config->setArguments(['arg' => 1]);

        $clone = clone $config;
        $clone->setBody('modified');
        $clone->setHeaders(['key' => 'changed']);
        $clone->setArguments(['arg' => 2]);

        $this->assertEquals('original', $config->getBody());
        $this->assertEquals(['key' => 'value'], $config->getHeaders());
        $this->assertEquals(['arg' => 1], $config->getArguments());
        $this->assertEquals('modified', $clone->getBody());
        $this->assertEquals(['key' => 'changed'], $clone->getHeaders());
        $this->assertEquals(['arg' => 2], $clone->getArguments());
    }

    public function testInvokeReturnsArray(): void
    {
        $config = new BuilderConfig();
        $config->setQueue('my-queue');
        $config->setBody('my-body');

        $array = $config();
        $this->assertIsArray($array);
        $this->assertEquals('my-queue', $array['_queue']);
        $this->assertEquals('my-body', $array['_body']);
        $this->assertArrayHasKey('_exchange', $array);
        $this->assertArrayHasKey('_exchangeType', $array);
        $this->assertArrayHasKey('_headers', $array);
        $this->assertArrayHasKey('_callback', $array);
    }

    public function testConstructFromArray(): void
    {
        $config = new BuilderConfig([
            '_queue'   => 'from-array',
            '_body'    => 'array-body',
            '_durable' => false,
        ]);

        $this->assertEquals('from-array', $config->getQueue());
        $this->assertEquals('array-body', $config->getBody());
        $this->assertFalse($config->isDurable());
        $this->assertEquals(Constants::DIRECT, $config->getExchangeType());
    }

    public function testConstructFromArrayIgnoresUnknownKeys(): void
    {
        $config = new BuilderConfig([
            '_queue'      => 'test',
            'unknown_key' => 'value',
        ]);

        $this->assertEquals('test', $config->getQueue());
    }
}
