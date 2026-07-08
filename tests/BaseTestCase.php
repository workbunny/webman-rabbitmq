<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use PHPUnit\Framework\TestCase;
use Webman\Context;
use Workbunny\WebmanRabbitMQ\Commands\AbstractCommand;
use Workbunny\WebmanRabbitMQ\ConnectionsManagement;

class BaseTestCase extends TestCase
{
    protected string $rabbitHost = 'http://rabbitmq:15672'; // RabbitMQ 管理插件地址
    protected string $rabbitUser = 'guest';                  // 用户名
    protected string $rabbitPass = 'guest';                  // 密码
    protected string $vhost = '/';                      // 默认 vhost

    protected array $config = [];

    protected function setUp(): void
    {
        $this->config = \config('plugin.workbunny.webman-rabbitmq.connections.default.config', []);
        $this->rabbitHost = "http://{$this->config['host']}:1{$this->config['port']}";
        $this->rabbitUser = $this->config['username'];
        $this->rabbitPass = $this->config['password'];
        $this->vhost = $this->config['vhost'];
    }

    protected function tearDown(): void
    {
        // 清理协程上下文，防止 channel 缓存泄漏到下一个测试
        if (class_exists(Context::class)) {
            Context::destroy();
        }
    }

    public static function setUpBeforeClass(): void
    {
        // 确保开始前连接池是干净的
        ConnectionsManagement::reset();
    }

    public static function tearDownAfterClass(): void
    {
        // 确保结束后连接池完全清空，不影响下一个测试类
        ConnectionsManagement::reset();
    }

    protected function exec(string $command): array
    {
        exec($command, $output, $resultCode);

        return [$output, $resultCode];
    }

    protected function fileIsset(string $name, bool $delayed): bool
    {
        list(, , $file) = AbstractCommand::getFileInfo($name, $delayed);

        return file_exists($file);
    }

    /**
     * @param string $path
     * @param string $method
     * @param array $data
     * @return array
     */
    protected function request(string $path, string $method = 'GET', array $data = []): array
    {
        $url = $this->rabbitHost . $path;
        $opts = [
            'http' => [
                'method' => $method,
                'header' => 'Authorization: Basic ' . base64_encode("{$this->rabbitUser}:{$this->rabbitPass}") . "\r\n" .
                    "Content-Type: application/json\r\n",
                'content' => $data ? json_encode($data) : '',
            ],
        ];
        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);

        return $result ? json_decode($result, true) : [];
    }

    /**
     * 验证 exchange 是否存在
     */
    protected function exchangeExists(string $exchange): bool
    {
        $path = '/api/exchanges/' . urlencode($this->vhost) . '/' . urlencode($exchange);
        $result = $this->request($path);

        return !empty($result) && isset($result['name']) && $result['name'] === $exchange;
    }

    /**
     * 验证 queue 是否存在
     */
    protected function queueExists(string $queue): bool
    {
        $path = '/api/queues/' . urlencode($this->vhost) . '/' . urlencode($queue);
        $result = $this->request($path);

        return !empty($result) && isset($result['name']) && $result['name'] === $queue;
    }

    /**
     * 验证队列是否包含消息
     */
    protected function queueHasMessages(string $queue): bool
    {
        $path = '/api/queues/' . urlencode($this->vhost) . '/' . urlencode($queue);
        $result = $this->request($path);

        return !empty($result) && isset($result['messages']) && $result['messages'] > 0;
    }

    /**
     * 获取队列中的消息（测试用）
     */
    protected function getQueueMessages(string $queue, int $count = 1, bool $ack = true): array
    {
        $path = '/api/queues/' . urlencode($this->vhost) . '/' . urlencode($queue) . '/get';

        return $this->request($path, 'POST', [
            'count'    => $count,
            'ackmode'  => $ack ? 'ack_requeue_false' : 'ack_requeue_true',
            'encoding' => 'auto',
        ]);
    }

    /**
     * 获取所有连接
     * @return array
     */
    protected function listConnections(): array
    {
        $path = '/api/connections';

        return $this->request($path);
    }

    protected function listChannels(): array
    {
        $path = '/api/channels';

        return $this->request($path);
    }
}
