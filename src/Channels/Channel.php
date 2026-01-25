<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */
namespace Workbunny\WebmanRabbitMQ\Channels;

use Bunny\ChannelStateEnum;
use Workerman\Timer;

class Channel extends \Bunny\Channel
{
    /**
     * 获取通道状态
     *
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * 设置通道状态
     *
     * @param int $state
     * @return void
     */
    public function setState(int $state): void
    {
        $this->state = $state;
    }

    /** @inheritDoc */
    public function close($replyCode = 0, $replyText = ""): bool
    {
        if ($this->state === ChannelStateEnum::CLOSED) {
            return true;
        }
        $this->setState(ChannelStateEnum::CLOSING);
        // 发送关闭信号
        $this->client->channelClose($this->channelId, $replyCode, $replyText, 0, 0);
        // 阻塞等待关闭
        while (1) {
            // 随机休眠 协程切换
            Timer::sleep(rand(10, 100) / 1000);
            if ($this->state === ChannelStateEnum::CLOSED) {
                return true;
            }
        }
    }
}