<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanRabbitMQ\Traits;

use Bunny\ChannelStateEnum;
use Bunny\Exception\ClientException;
use Bunny\Protocol\MethodChannelOpenOkFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use React\Promise\PromiseInterface;
use Workbunny\WebmanRabbitMQ\Channels\Channel;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQConnectException;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;
use Workerman\Timer;

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

    /**
     * 获取一个可用的通道
     *
     * @param bool $reuse
     * @param int $min
     * @param int $max
     * @return Channel
     * @throws WebmanRabbitMQConnectException
     */
    public function catchChannel(bool $reuse = false, int $min = 10, int $max = 100): Channel
    {
        $resChannel = null;
        // 从已创建的频道中获取一个可用的频道
        $channels = $reuse ? $this->getChannels() : [];
        foreach ($channels as  $channel) {
            if ($channel->getState() === ChannelStateEnum::READY) {
                $resChannel = $channel;
                break;
            }
        }
        // 如果已创建的频道已满，则等待
        if ($this->getChannelLimit() <= count($this->getChannels())) {
            Timer::sleep(rand($min, $max) / 1000);
        }
        // 如果没有可用的频道，则创建一个新频道
        if ($resChannel) {
            return $resChannel;

        }
        $channelId = $this->findChannelId();
        $resChannel = new Channel($this, $channelId);
        try {
            $response = $this->channelOpen($channelId);
            if ($response instanceof MethodChannelOpenOkFrame) {
                return $this->channels[$channelId] = $resChannel;
            }
            throw new WebmanRabbitMQConnectException("Channel open failed.");
        } catch (ClientException $e) {
            throw new WebmanRabbitMQConnectException($e->getMessage(), $e->getCode(), $e);
        }
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