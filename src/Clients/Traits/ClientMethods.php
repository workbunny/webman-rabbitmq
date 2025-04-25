<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanRabbitMQ\Clients\Traits;


use Bunny\Channel as OriginalChannel;
use Bunny\ChannelStateEnum;
use Bunny\Client;
use Bunny\ClientStateEnum;
use Bunny\Exception\ClientException;
use Bunny\Protocol\MethodChannelOpenOkFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Workbunny\WebmanRabbitMQ\Clients\Channels\Channel as CurrentChannel;
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
     * @return OriginalChannel[]|CurrentChannel[]
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * 获取一个可用的通道
     *
     * @param bool $reuse
     * @return CurrentChannel|PromiseInterface
     */
    public function catchChannel(bool $reuse = false): CurrentChannel|PromiseInterface
    {
        $resChannel = null;
        // 从已创建的频道中获取一个可用的频道
        $channels = $reuse ? $this->getChannels() : [];
        foreach ($channels as $channel) {
            if (
                $channel instanceof CurrentChannel and
                $channel->getState() === ChannelStateEnum::READY
            ) {
                $resChannel = $channel;
                break;
            }
        }
        // 如果没有可用的频道，则创建一个新频道
        if (!$resChannel) {
            $channelId = $this->findChannelId();
            $this->channels[$channelId] = new CurrentChannel($this, $channelId);
            $response = $this->channelOpen($channelId);
            if ($response instanceof MethodChannelOpenOkFrame) {
                return $this->channels[$channelId];

            } elseif ($response instanceof PromiseInterface) {
                return $response->then(function () use ($channelId) {
                    return $this->channels[$channelId];
                });
            } else {
                $this->state = ClientStateEnum::ERROR;
                throw new ClientException(
                    "channel.open unexpected response of type " . gettype($response) .
                    (is_object($response) ? "(" . get_class($response) . ")" : "") .
                    "."
                );
            }
        }
        return ($this instanceof Client)
            ? $resChannel
            : new Promise(function () use ($resChannel) {
                return $resChannel;
            });
    }

    /** @inheritdoc  */
    protected function read(): void
    {
        if (!$this->stream) {
            throw new WebmanRabbitMQException("Stream is not connected.");
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