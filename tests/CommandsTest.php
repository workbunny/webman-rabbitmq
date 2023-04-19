<?php
declare(strict_types=1);

namespace Tests;

use function Workbunny\WebmanRabbitMQ\config;

/**
 * @runTestsInSeparateProcesses
 */
class CommandsTest extends BaseTest
{
    /**
     * @return void
     */
    public function testRabbitMQBuilderNormal()
    {
        $name = 'test';
        $this->expectOutputString(
            "ℹ️ Run in debug mode!\n" .
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php\n" .
            "ℹ️ Builder created. /var/www/process/workbunny/rabbitmq/TestBuilder.php\n" .
            "✅ Builder TestBuilder created successfully.\n"
        );
        $this->assertFalse($this->configIsset($name, false));
        $this->assertFalse($this->fileIsset($name, false));
        list(, $status) = $this->passthru("php bin/command workbunny:rabbitmq-builder $name");
        $this->assertEquals(0, $status);
        $this->assertTrue($this->fileIsset($name, false));
    }

    /**
     * @depends testRabbitMQBuilderNormal
     * @return void
     */
    public function testRabbitMQRemoveNormal()
    {
        $name = 'test';
        $this->expectOutputString(
            "ℹ️ Run in debug mode!\n" .
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php\n" .
            "ℹ️ Builder removed. /var/www/process/workbunny/rabbitmq/TestBuilder.php\n" .
            "✅ Builder TestBuilder removed successfully.\n"
        );
        $this->assertTrue($this->configIsset($name, false));
        $this->assertTrue($this->fileIsset($name, false));
        list(, $status) = $this->passthru("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertFalse($this->fileIsset($name, false));
    }

    /**
     * @return void
     */
    public function testRabbitMQBuilderMultilevel()
    {
        $name = 'test/test';
        $this->expectOutputString(
            "ℹ️ Run in debug mode!\n" .
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php\n" .
            "ℹ️ Builder created. /var/www/process/workbunny/rabbitmq/test/TestBuilder.php\n" .
            "✅ Builder TestBuilder created successfully.\n"
        );
        $this->assertFalse($this->configIsset($name, false));
        $this->assertFalse($this->fileIsset($name, false));
        list(, $status) = $this->passthru("php bin/command workbunny:rabbitmq-builder $name");
        $this->assertEquals(0, $status);
        $this->assertTrue($this->fileIsset($name, false));
    }

    /**
     * @depends testRabbitMQBuilderNormal
     * @return void
     */
    public function testRabbitMQRemoveMultilevel()
    {
        $name = 'test/test';
        $this->expectOutputString(
            "ℹ️ Run in debug mode!\n" .
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php\n" .
            "ℹ️ Builder removed. /var/www/process/workbunny/rabbitmq/test/TestBuilder.php\n" .
            "✅ Builder TestBuilder removed successfully.\n"
        );
        $this->assertTrue($this->configIsset($name, false));
        $this->assertTrue($this->fileIsset($name, false));
        list(, $status) = $this->passthru("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertFalse($this->fileIsset($name, false));
    }

    /**
     * @return void
     */
    public function testRabbitMQBuilderDelayed()
    {
        $name = 'test -d';
        $this->expectOutputString(
            "ℹ️ Run in debug mode!\n" .
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php\n" .
            "ℹ️ Builder created. /var/www/process/workbunny/rabbitmq/TestBuilderDelayed.php\n" .
            "✅ Builder TestBuilderDelayed created successfully.\n"
        );
        $this->assertFalse($this->configIsset($name, false));
        $this->assertFalse($this->fileIsset($name, false));
        list(, $status) = $this->passthru("php bin/command workbunny:rabbitmq-builder $name");
        $this->assertEquals(0, $status);
        $this->assertTrue($this->fileIsset($name, false));
    }

    /**
     * @depends testRabbitMQBuilderNormal
     * @return void
     */
    public function testRabbitMQRemoveDelayed()
    {
        $name = 'test -d';
        $this->expectOutputString(
            "ℹ️ Run in debug mode!\n" .
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php\n" .
            "ℹ️ Builder removed. /var/www/process/workbunny/rabbitmq/TestBuilderDelayed.php\n" .
            "✅ Builder TestBuilderDelayed removed successfully.\n"
        );
        $this->assertTrue($this->configIsset($name, false));
        $this->assertTrue($this->fileIsset($name, false));
        list(, $status) = $this->passthru("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertFalse($this->fileIsset($name, false));
    }

    /**
     * @return void
     */
    public function testRabbitMQBuilderMultilevelDelayed()
    {
        $name = 'test/test -d';
        $this->expectOutputString(
            "ℹ️ Run in debug mode!\n" .
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php\n" .
            "ℹ️ Builder created. /var/www/process/workbunny/rabbitmq/test/TestBuilderDelayed.php\n" .
            "✅ Builder TestBuilderDelayed created successfully.\n"
        );
        $this->assertFalse($this->configIsset($name, false));
        $this->assertFalse($this->fileIsset($name, false));
        list(, $status) = $this->passthru("php bin/command workbunny:rabbitmq-builder $name");
        $this->assertEquals(0, $status);
        $this->assertTrue($this->fileIsset($name, false));
    }

    /**
     * @depends testRabbitMQBuilderNormal
     * @return void
     */
    public function testRabbitMQRemoveMultilevelDelayed()
    {
        $name = 'test/test -d';
        $this->expectOutputString(
            "ℹ️ Run in debug mode!\n" .
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php\n" .
            "ℹ️ Builder removed. /var/www/process/workbunny/rabbitmq/test/TestBuilderDelayed.php\n" .
            "✅ Builder TestBuilderDelayed removed successfully.\n"
        );
        $this->assertTrue($this->configIsset($name, false));
        $this->assertTrue($this->fileIsset($name, false));
        list(, $status) = $this->passthru("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertFalse($this->fileIsset($name, false));
    }

}