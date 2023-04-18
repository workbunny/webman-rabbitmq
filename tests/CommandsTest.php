<?php
declare(strict_types=1);

namespace Tests;
class CommandsTest extends BaseTest
{
    public static function provider(): array
    {
        return [
            [true, false]
        ];
    }

    /**
     * @dataProvider provider
     * @return void
     */
    public function testBuilder(bool $delayed)
    {
        $name = 'test';
        $this->assertFalse($this->configIsset($name, $delayed));
        $this->assertFalse($this->fileIsset($name, $delayed));
        $this->exec("php command workbunny:rabbitmq-builder $name");
        $this->assertTrue($this->configIsset($name, $delayed));
        $this->assertTrue($this->fileIsset($name, $delayed));
    }

    /**
     * @dataProvider provider
     * @return void
     */
    public function testBuilderMultilevel(bool $delayed)
    {
        $name = 'test/test';
        $this->assertFalse($this->configIsset($name, $delayed));
        $this->assertFalse($this->fileIsset($name, $delayed));
        $this->exec("php command workbunny:rabbitmq-builder $name");
        $this->assertTrue($this->configIsset($name, $delayed));
        $this->assertTrue($this->fileIsset($name, $delayed));
    }
}