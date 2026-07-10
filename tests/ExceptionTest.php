<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelFulledException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQPublishException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQRequeueException;

class ExceptionTest extends BaseTestCase
{
    /**
     * 回归测试问题2：非协程上下文下 Coroutine::getCurrent() 返回 null 不应 NPE
     */
    public function testExceptionInNonCoroutineContext(): void
    {
        $exception = new WebmanRabbitMQException('test message');
        $this->assertStringContainsString('test message', $exception->getMessage());
        $this->assertStringContainsString('[Co:', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('prev');
        $exception = new WebmanRabbitMQException('msg', 42, $previous);
        $this->assertEquals(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionExtra(): void
    {
        $exception = new WebmanRabbitMQException('msg', 0, null, ['key' => 'value']);
        $ref = new \ReflectionProperty($exception, 'extra');
        $ref->setAccessible(true);
        $this->assertEquals(['key' => 'value'], $ref->getValue($exception));
    }

    public function testExceptionInheritanceChain(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new WebmanRabbitMQException(''));
        $this->assertInstanceOf(WebmanRabbitMQException::class, new WebmanRabbitMQConnectException(''));
        $this->assertInstanceOf(WebmanRabbitMQException::class, new WebmanRabbitMQChannelException(''));
        $this->assertInstanceOf(WebmanRabbitMQException::class, new WebmanRabbitMQChannelFulledException(''));
        $this->assertInstanceOf(WebmanRabbitMQException::class, new WebmanRabbitMQPublishException(''));
        $this->assertInstanceOf(WebmanRabbitMQPublishException::class, new WebmanRabbitMQRequeueException(''));
    }
}
