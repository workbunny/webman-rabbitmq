<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;
use Workbunny\WebmanRabbitMQ\Builders\QueueBuilder;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;

class BuilderModeTest extends BaseTestCase
{
    public function testDefaultMode(): void
    {
        $this->assertEquals(QueueBuilder::class, AbstractBuilder::getMode('queue'));
    }

    public function testGetModeNotExist(): void
    {
        $this->assertNull(AbstractBuilder::getMode('nonexistent-mode'));
    }

    /**
     * 回归测试问题1：registerMode() 应实际写入 static::$modes
     */
    public function testRegisterMode(): void
    {
        $result = AbstractBuilder::registerMode('test-custom-mode', QueueBuilder::class);
        $this->assertArrayHasKey('test-custom-mode', $result);
        $this->assertEquals(QueueBuilder::class, $result['test-custom-mode']);
        $this->assertEquals(QueueBuilder::class, AbstractBuilder::getMode('test-custom-mode'));
    }

    public function testRegisterModeInvalidClass(): void
    {
        $this->expectException(WebmanRabbitMQException::class);
        AbstractBuilder::registerMode('test-invalid-mode', \stdClass::class);
    }
}
