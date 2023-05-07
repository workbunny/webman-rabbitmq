<?php declare(strict_types=1);

namespace Workbunny\Tests;

use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;

/**
 * @runTestsInSeparateProcesses
 */
final class CommandsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        AbstractBuilder::$debug = true;
        parent::setUp();
    }

    /**
     * @testdox 测试Builder的创建和移除
     * @return void
     */
    public function testBuilderNormal(): void
    {
        $name = 'test';
        // create
        $this->assertFalse($this->fileIsset($name, false));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-builder $name");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated." ,
            "ℹ️ Builder created." ,
            "✅ Builder TestBuilder created successfully."
        ], $result);
        $this->assertTrue($this->fileIsset($name, false));
        // remove
        $this->assertTrue($this->fileIsset($name, false));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated." ,
            "ℹ️ Builder removed." ,
            "✅ Builder TestBuilder removed successfully."
        ], $result);
        $this->assertFalse($this->fileIsset($name, false));
    }

    /**
     * @testdox 测试多层级Builder的创建和移除
     * @return void
     */
    public function testBuilderMultilevel()
    {
        $name = 'test/test';
        // create
        $this->assertFalse($this->fileIsset($name, false));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-builder $name");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated." ,
            "ℹ️ Builder created." ,
            "✅ Builder TestBuilder created successfully."
        ], $result);
        $this->assertTrue($this->fileIsset($name, false));
        // remove
        $this->assertTrue($this->fileIsset($name, false));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated." ,
            "ℹ️ Builder removed." ,
            "ℹ️ Empty dir removed.",
            "✅ Builder TestBuilder removed successfully."
        ], $result);
        $this->assertFalse($this->fileIsset($name, false));
    }

    /**
     * @testdox 测试DelayedBuilder的创建和移除
     * @return void
     */
    public function testDelayedBuilder()
    {
        $name = 'test';
        // create
        $this->assertFalse($this->fileIsset($name, true));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-builder $name -d");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated." ,
            "ℹ️ Builder created." ,
            "✅ Builder TestBuilderDelayed created successfully."
        ], $result);
        $this->assertTrue($this->fileIsset($name, true));
        // remove
        $this->assertTrue($this->fileIsset($name, true));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-remove $name -d");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated." ,
            "ℹ️ Builder removed." ,
            "✅ Builder TestBuilderDelayed removed successfully."
        ], $result);
        $this->assertFalse($this->fileIsset($name, true));
    }

    /**
     * @testdox 测试多层级DelayedBuilder的创建和移除
     * @return void
     */
    public function testDelayedBuilderMultilevel()
    {
        $name = 'test/test';
        // create
        $this->assertFalse($this->fileIsset($name, true));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-builder $name -d");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated." ,
            "ℹ️ Builder created." ,
            "✅ Builder TestBuilderDelayed created successfully."
        ], $result);
        $this->assertTrue($this->fileIsset($name, true));
        // remove
        $this->assertTrue($this->fileIsset($name, true));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-remove $name -d");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated." ,
            "ℹ️ Builder removed." ,
            "ℹ️ Empty dir removed.",
            "✅ Builder TestBuilderDelayed removed successfully."
        ], $result);
        $this->assertFalse($this->fileIsset($name, true));
    }
}