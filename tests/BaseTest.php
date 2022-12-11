<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Workbunny\WebmanRabbitMQ\SyncConnection;

abstract class BaseTest extends TestCase
{
    protected function setUp(): void
    {
        TestBuilder::$debug = true;
        parent::setUp();
    }
}