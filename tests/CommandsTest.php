<?php
declare(strict_types=1);

namespace Tests;

/**
 * @runTestsInSeparateProcesses
 */
class CommandsTest extends BaseTest
{
    /**
     * @return void
     */
    public function testBuilderNormal()
    {
        $name = 'test';
        // create
        $this->assertFalse($this->fileIsset($name, false));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-builder $name");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php" ,
            "ℹ️ Builder created. /var/www/process/workbunny/rabbitmq/TestBuilder.php" ,
            "✅ Builder TestBuilder created successfully."
        ], $result);
        $this->assertTrue($this->fileIsset($name, false));
        // remove
        $this->assertTrue($this->fileIsset($name, false));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php" ,
            "ℹ️ Builder removed. /var/www/process/workbunny/rabbitmq/TestBuilder.php" ,
            "✅ Builder TestBuilder removed successfully."
        ], $result);
        $this->assertFalse($this->fileIsset($name, false));
    }

    /**
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
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php" ,
            "ℹ️ Builder created. /var/www/process/workbunny/rabbitmq/test/TestBuilder.php" ,
            "✅ Builder TestBuilder created successfully."
        ], $result);
        $this->assertTrue($this->fileIsset($name, false));
        // remove
        $this->assertTrue($this->fileIsset($name, false));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php" ,
            "ℹ️ Builder removed. /var/www/process/workbunny/rabbitmq/test/TestBuilder.php" ,
            "ℹ️ Empty dir removed. /var/www/process/workbunny/rabbitmq/test",
            "✅ Builder TestBuilder removed successfully."
        ], $result);
        $this->assertFalse($this->fileIsset($name, false));
    }

    /**
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
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php" ,
            "ℹ️ Builder created. /var/www/process/workbunny/rabbitmq/TestBuilderDelayed.php" ,
            "✅ Builder TestBuilderDelayed created successfully."
        ], $result);
        $this->assertTrue($this->fileIsset($name, true));
        // remove
        $this->assertTrue($this->fileIsset($name, true));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-remove $name -d");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php" ,
            "ℹ️ Builder removed. /var/www/process/workbunny/rabbitmq/TestBuilderDelayed.php" ,
            "✅ Builder TestBuilderDelayed removed successfully."
        ], $result);
        $this->assertFalse($this->fileIsset($name, true));
    }

    /**
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
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php" ,
            "ℹ️ Builder created. /var/www/process/workbunny/rabbitmq/test/TestBuilderDelayed.php" ,
            "✅ Builder TestBuilderDelayed created successfully."
        ], $result);
        $this->assertTrue($this->fileIsset($name, true));
        // remove
        $this->assertTrue($this->fileIsset($name, true));
        list($result, $status) = $this->exec("php bin/command workbunny:rabbitmq-remove $name");
        $this->assertEquals(0, $status);
        $this->assertEquals([
            "ℹ️ Run in debug mode!" ,
            "ℹ️ Config updated. /var/www/src/config/plugin/workbunny/webman-rabbitmq/process.php" ,
            "ℹ️ Builder removed. /var/www/process/workbunny/rabbitmq/test/TestBuilderDelayed.php" ,
            "ℹ️ Empty dir removed. /var/www/process/workbunny/rabbitmq/test",
            "✅ Builder TestBuilderDelayed removed successfully."
        ], $result);
        $this->assertFalse($this->fileIsset($name, true));
    }
}