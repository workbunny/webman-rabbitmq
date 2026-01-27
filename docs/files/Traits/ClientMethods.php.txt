<?php

declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanRabbitMQ\Traits;

use Bunny\ChannelStateEnum;
use Bunny\Protocol\MethodConnectionStartFrame;
use React\Promise\PromiseInterface;
use Workbunny\WebmanRabbitMQ\Channels\Channel;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;

trait ClientMethods
{
    /**
     * 获取通道数量限制
     *
     * @return int
     */
    public function getChannelLimit(): int
    {
        return $this->channelMax;
    }

    /**
     * 获取已创建的通道
     *
     * @return Channel[]
     */
    public function getChannels(): array
    {
        /**
         * @var int $id
         * @var Channel $channel
         */
        foreach ($this->channels as $id => $channel) {
            if ($channel->getState() === ChannelStateEnum::CLOSED) {
                unset($this->channels[$id]);
            }
        }

        return $this->channels;
    }

    /** @inheritdoc  */
    protected function read(): void
    {
        if (!$this->stream) {
            throw new WebmanRabbitMQException('Stream is not connected.');
        }
        parent::read();
    }

    /**
     * 重写authResponse方法
     *  1. 支持PLAIN及AMQPLAIN两种机制
     *
     * @param MethodConnectionStartFrame $start
     * @return bool|PromiseInterface
     * @inheritdoc
     */
    abstract protected function authResponse(MethodConnectionStartFrame $start);

    /**
     * 回收
     */
    abstract protected function __destruct();
}
