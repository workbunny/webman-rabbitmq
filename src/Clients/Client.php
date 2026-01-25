<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Clients;

use Bunny\ChannelStateEnum;
use Bunny\ClientStateEnum;
use Bunny\Protocol\MethodChannelOpenOkFrame;
use Webman\Context;
use Workbunny\WebmanRabbitMQ\Channels\Channel;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQChannelException;
use Workerman\Coroutine;
use Workerman\Coroutine\Pool;

class Client extends AbstractClient
{
    /**
     * @var Pool|null
     */
    protected ?Pool $channelsPool = null;

    /**
     * 获取通道池
     *
     * @return Pool|null
     */
    public function channelsPool(): ?Pool
    {
        return $this->channelsPool;
    }

    /**
     * 获取通道
     *
     * @return Channel
     * @throws WebmanRabbitMQChannelException
     */
    public function channel(): Channel
    {
        if (!$this->channelsPool) {
            $this->channelsPool = new Pool($this->getChannelLimit(), $this->getConfig('channels_pool', []));
            $this->channelsPool->setConnectionCreator(function () {
                $channelId = $this->findChannelId();

                $this->channels[$channelId] = new Channel($this, $channelId);
                $response = $this->channelOpen($channelId);

                if ($response instanceof MethodChannelOpenOkFrame) {
                    return $this->channels[$channelId];
                } else {
                    $this->state = ClientStateEnum::ERROR;
                    throw new WebmanRabbitMQChannelException(
                        "channel.open unexpected response of type " . gettype($response) .
                        (is_object($response) ? "(" . get_class($response) . ")" : "") .
                        "."
                    );
                }
            });
            $this->channelsPool->setConnectionCloser(function (Channel $channel) {
                try {
                    $channel->close();
                    unset($this->channels[$channel->getChannelId()]);
                } catch (\Throwable) {}
            });
        }
        $channel = Context::get('workbunny.webman-rabbitmq.channel');
        if (!$channel) {
            try {
                /** @var Channel $channel */
                $channel = $this->channelsPool->get();
            } catch (Coroutine\Exception\PoolException | \Throwable) {
                throw new WebmanRabbitMQChannelException("No available channel.", -999999999);
            }
            Context::set('workbunny.webman-rabbitmq.channel', $channel);
            Coroutine::defer(function () use ($channel) {
                try {
                    if (in_array($channel->getState(), [ChannelStateEnum::ERROR, ChannelStateEnum::CLOSED, ChannelStateEnum::CLOSING])) {
                        $this->channelsPool->closeConnection($channel);
                    } else {
                        $this->channelsPool->put($channel);
                    }
                } catch (\Throwable) {}
            });
            return $channel;
        }
        return $channel;
    }
}