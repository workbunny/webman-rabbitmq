<?php

declare(strict_types=1);

namespace Workbunny\Tests;

use PHPUnit\Framework\TestCase;
use Workbunny\WebmanRabbitMQ\Commands\AbstractCommand;

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
        // HTTP-API存在延迟，这里延迟10秒等待数据可查
        sleep(10);
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
    protected function getQueueMessages(string $queue, int $count = 1): array
    {
        $path = '/api/queues/' . urlencode($this->vhost) . '/' . urlencode($queue) . '/get';

        return $this->request($path, 'POST', [
            'count'    => $count,
            'ackmode'  => 'ack_requeue_false',
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
