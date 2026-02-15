<?php

declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanRabbitMQ\Connection\Traits;

use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicCancelFrame;
use Bunny\Protocol\MethodBasicConsumeFrame;
use Bunny\Protocol\MethodBasicGetFrame;
use Bunny\Protocol\MethodBasicNackFrame;
use Bunny\Protocol\MethodBasicPublishFrame;
use Bunny\Protocol\MethodBasicQosFrame;
use Bunny\Protocol\MethodBasicRecoverFrame;
use Bunny\Protocol\MethodBasicRejectFrame;
use Bunny\Protocol\MethodChannelCloseFrame;
use Bunny\Protocol\MethodChannelCloseOkFrame;
use Bunny\Protocol\MethodChannelFlowFrame;
use Bunny\Protocol\MethodChannelFlowOkFrame;
use Bunny\Protocol\MethodChannelOpenFrame;
use Bunny\Protocol\MethodConfirmSelectFrame;
use Bunny\Protocol\MethodExchangeBindFrame;
use Bunny\Protocol\MethodExchangeBindOkFrame;
use Bunny\Protocol\MethodExchangeDeclareFrame;
use Bunny\Protocol\MethodExchangeDeclareOkFrame;
use Bunny\Protocol\MethodExchangeDeleteFrame;
use Bunny\Protocol\MethodExchangeDeleteOkFrame;
use Bunny\Protocol\MethodExchangeUnbindFrame;
use Bunny\Protocol\MethodExchangeUnbindOkFrame;
use Bunny\Protocol\MethodQueueBindFrame;
use Bunny\Protocol\MethodQueueBindOkFrame;
use Bunny\Protocol\MethodQueueDeclareFrame;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use Bunny\Protocol\MethodQueueDeleteFrame;
use Bunny\Protocol\MethodQueueDeleteOkFrame;
use Bunny\Protocol\MethodQueuePurgeFrame;
use Bunny\Protocol\MethodQueuePurgeOkFrame;
use Bunny\Protocol\MethodQueueUnbindFrame;
use Bunny\Protocol\MethodTxCommitFrame;
use Bunny\Protocol\MethodTxRollbackFrame;
use Bunny\Protocol\MethodTxSelectFrame;

trait ChannelsMethods
{
    /**
     * send AMQP channel.open frame
     *
     * @param int $channel
     * @param string $outOfBand
     * @return bool
     */
    public function channelOpen(int $channel, string $outOfBand = ''): bool
    {
        $f = new MethodChannelOpenFrame();
        $f->channel = $channel;
        $f->outOfBand = $outOfBand;

        return $this->frameSend($f);
    }

    /**
     * send AMQP channel.flow frame
     *
     * @param int $channel
     * @param bool $active
     * @return bool
     */
    public function channelFlow(int $channel, bool $active): bool
    {
        $f = new MethodChannelFlowFrame();
        $f->channel = $channel;
        $f->active = $active;

        return $this->frameSend($f);
    }

    /**
     * send AMQP channel.flow-ok frame
     *
     * @param int $channel
     * @param bool $active
     * @return bool
     */
    public function channelFlowOk(int $channel, bool $active): bool
    {
        $f = new MethodChannelFlowOkFrame();
        $f->channel = $channel;
        $f->active = $active;

        return $this->frameSend($f);
    }

    /**
     * send AMQP channel.close frame
     *
     * @param int $channel
     * @param int $replyCode
     * @param string $replyText
     * @param int $closeClassId
     * @param int $closeMethodId
     * @return bool
     */
    public function channelClose(int $channel, int $replyCode, string $replyText, int $closeClassId, int $closeMethodId): bool
    {
        $f = new MethodChannelCloseFrame();
        $f->channel = $channel;
        $f->replyCode = $replyCode;
        $f->replyText = $replyText;
        $f->closeClassId = $closeClassId;
        $f->closeMethodId = $closeMethodId;

        return $this->frameSend($f);
    }

    /**
     * send AMQP channel.close-ok frame
     *
     * @param int $channel
     * @return bool
     */
    public function channelCloseOk(int $channel): bool
    {
        $f = new MethodChannelCloseOkFrame();
        $f->channel = $channel;

        return $this->frameSend($f);
    }

    /**
     * send AMQP exchange.declare frame
     *
     * @param int $channel
     * @param string $exchange
     * @param string $exchangeType
     * @param bool $passive
     * @param bool $durable
     * @param bool $autoDelete
     * @param bool $internal
     * @param bool $nowait
     * @param array $arguments
     * @return bool|MethodExchangeDeclareOkFrame
     */
    public function exchangeDeclare(
        int    $channel,
        string $exchange,
        string $exchangeType = 'direct',
        bool $passive = false,
        bool $durable = false,
        bool   $autoDelete = false,
        bool $internal = false,
        bool $nowait = false,
        array $arguments = []
    ): bool|MethodExchangeDeclareOkFrame {
        $f = new MethodExchangeDeclareFrame();
        $f->channel = $channel;
        $f->exchange = $exchange;
        $f->exchangeType = $exchangeType;
        $f->passive = $passive;
        $f->durable = $durable;
        $f->autoDelete = $autoDelete;
        $f->internal = $internal;
        $f->nowait = $nowait;
        $f->arguments = $arguments;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            /** @var MethodExchangeDeclareOkFrame $f */
            $f = $this->connection->await(MethodExchangeDeclareOkFrame::class, function (MethodExchangeDeclareOkFrame $frame) use ($channel) {
                return $frame->channel === $channel;
            });

            return $f;
        }

        return $res;
    }

    /**
     * send AMQP exchange.delete frame
     *
     * @param int $channel
     * @param string $exchange
     * @param bool $ifUnused
     * @param bool $nowait
     * @return bool|MethodExchangeDeleteOkFrame
     */
    public function exchangeDelete(int $channel, string $exchange, bool $ifUnused = false, bool $nowait = false): bool|MethodExchangeDeleteOkFrame
    {
        $f = new MethodExchangeDeleteFrame();
        $f->channel = $channel;
        $f->exchange = $exchange;
        $f->ifUnused = $ifUnused;
        $f->nowait = $nowait;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            /** @var MethodExchangeDeleteOkFrame $f */
            $f = $this->connection->await(MethodExchangeDeleteOkFrame::class, function (MethodExchangeDeleteOkFrame $frame) use ($channel) {
                return $frame->channel === $channel;
            });

            return $f;
        }

        return $res;
    }

    /**
     * send AMQP exchange.bind frame
     *
     * @param int $channel
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param bool $nowait
     * @param array $arguments
     * @return bool|MethodExchangeBindOkFrame
     */
    public function exchangeBind(
        int $channel,
        string $destination,
        string $source,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = []
    ): bool|MethodExchangeBindOkFrame {
        $f = new MethodExchangeBindFrame();
        $f->channel = $channel;
        $f->destination = $destination;
        $f->source = $source;
        $f->routingKey = $routingKey;
        $f->nowait = $nowait;
        $f->arguments = $arguments;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            /** @var MethodExchangeBindOkFrame $f */
            $f = $this->connection->await(MethodExchangeBindOkFrame::class, function (MethodExchangeBindOkFrame $frame) use ($channel) {
                return $frame->channel === $channel;
            });

            return $f;
        }

        return $res;
    }

    /**
     * send AMQP exchange.unbind frame
     *
     * @param int $channel
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param bool $nowait
     * @param array $arguments
     * @return bool|MethodExchangeUnbindOkFrame
     */
    public function exchangeUnbind(
        int $channel,
        string $destination,
        string $source,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = []
    ): bool|MethodExchangeUnbindOkFrame {
        $f = new MethodExchangeUnbindFrame();
        $f->channel = $channel;
        $f->destination = $destination;
        $f->source = $source;
        $f->routingKey = $routingKey;
        $f->nowait = $nowait;
        $f->arguments = $arguments;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            /** @var MethodExchangeUnbindOkFrame $f */
            $f = $this->connection->await(MethodExchangeUnbindOkFrame::class, function (MethodExchangeUnbindOkFrame $frame) use ($channel) {
                return $frame->channel === $channel;
            });

            return $f;
        }

        return $res;
    }

    /**
     * send AMQP queue.declare frame
     *
     * @param int $channel
     * @param string $queue
     * @param bool $passive
     * @param bool $durable
     * @param bool $exclusive
     * @param bool $autoDelete
     * @param bool $nowait
     * @param array $arguments
     * @return bool|MethodQueueDeclareOkFrame
     */
    public function queueDeclare(
        int    $channel,
        string $queue = '',
        bool $passive = false,
        bool $durable = false,
        bool   $exclusive = false,
        bool $autoDelete = false,
        bool   $nowait = false,
        array $arguments = []
    ): bool|MethodQueueDeclareOkFrame {
        $f = new MethodQueueDeclareFrame();
        $f->channel = $channel;
        $f->queue = $queue;
        $f->passive = $passive;
        $f->durable = $durable;
        $f->exclusive = $exclusive;
        $f->autoDelete = $autoDelete;
        $f->nowait = $nowait;
        $f->arguments = $arguments;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            /** @var MethodQueueDeclareOkFrame $f */
            $f = $this->connection->await(MethodQueueDeclareOkFrame::class, function (MethodQueueDeclareOkFrame $frame) use ($channel) {
                return $frame->channel === $channel;
            });

            return $f;
        }

        return $res;
    }

    /**
     * send AMQP queue.bind frame
     *
     * @param int $channel
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param bool $nowait
     * @param array $arguments
     * @return bool|MethodQueueBindOkFrame
     */
    public function queueBind(
        int $channel,
        string $queue,
        string $exchange,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = []
    ): bool|MethodQueueBindOkFrame {
        $f = new MethodQueueBindFrame();
        $f->channel = $channel;
        $f->queue = $queue;
        $f->exchange = $exchange;
        $f->routingKey = $routingKey;
        $f->nowait = $nowait;
        $f->arguments = $arguments;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            /** @var MethodQueueBindOkFrame $f */
            $f = $this->connection->await(MethodQueueBindOkFrame::class, function (MethodQueueBindOkFrame $f) use ($channel) {
                return $f->channel === $channel;
            });

            return $f;
        }

        return $res;
    }

    /**
     * send AMQP queue.unbind frame
     *
     * @param int $channel
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param array $arguments
     * @return bool
     */
    public function queueUnbind(
        int $channel,
        string $queue,
        string $exchange,
        string $routingKey = '',
        array $arguments = []
    ): bool {
        $f = new MethodQueueUnbindFrame();
        $f->channel = $channel;
        $f->queue = $queue;
        $f->exchange = $exchange;
        $f->routingKey = $routingKey;
        $f->arguments = $arguments;

        return $this->frameSend($f);
    }

    /**
     * send AMQP queue.purge frame
     *
     * @param int $channel
     * @param string $queue
     * @param bool $nowait
     * @return bool|MethodQueuePurgeOkFrame
     */
    public function queuePurge(int $channel, string $queue = '', bool $nowait = false): bool|MethodQueuePurgeOkFrame
    {
        $f = new MethodQueuePurgeFrame();
        $f->channel = $channel;
        $f->queue = $queue;
        $f->nowait = $nowait;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            /** @var MethodQueuePurgeOkFrame $f */
            $f = $this->connection->await(MethodQueuePurgeOkFrame::class, function (MethodQueuePurgeOkFrame $frame) use ($channel) {
                return $frame->channel === $channel;
            });

            return $f;
        }

        return $res;
    }

    /**
     * send AMQP queue.delete frame
     *
     * @param int $channel
     * @param string $queue
     * @param bool $ifUnused
     * @param bool $ifEmpty
     * @param bool $nowait
     * @return bool|MethodQueueDeleteOkFrame
     */
    public function queueDelete(
        int $channel,
        string $queue = '',
        bool $ifUnused = false,
        bool $ifEmpty = false,
        bool $nowait = false
    ): bool|MethodQueueDeleteOkFrame {
        $f = new MethodQueueDeleteFrame();
        $f->channel = $channel;
        $f->queue = $queue;
        $f->ifUnused = $ifUnused;
        $f->ifEmpty = $ifEmpty;
        $f->nowait = $nowait;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            /** @var MethodQueueDeleteOkFrame $f */
            $f = $this->connection->await(MethodQueueDeleteOkFrame::class, function (MethodQueueDeleteOkFrame $frame) use ($channel) {
                return $frame->channel === $channel;
            });

            return $f;
        }

        return $res;
    }

    /**
     * send AMQP basic.qos frame
     *
     * @param int $channel
     * @param int $prefetchSize
     * @param int $prefetchCount
     * @param bool $global
     * @return bool
     */
    public function basicQos(int $channel, int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): bool
    {
        $f = new MethodBasicQosFrame();
        $f->channel = $channel;
        $f->prefetchSize = $prefetchSize;
        $f->prefetchCount = $prefetchCount;
        $f->global = $global;

        return $this->frameSend($f);
    }

    /**
     * send AMQP basic.consume frame
     *
     * @param int $channel
     * @param string $queue
     * @param string $consumerTag
     * @param bool $noLocal
     * @param bool $noAck
     * @param bool $exclusive
     * @param bool $nowait
     * @param array $arguments
     * @return bool
     */
    public function basicConsume(
        int $channel,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $nowait = false,
        array $arguments = []
    ): bool {
        $f = new MethodBasicConsumeFrame();
        $f->channel = $channel;
        $f->queue = $queue;
        $f->consumerTag = $consumerTag;
        $f->noLocal = $noLocal;
        $f->noAck = $noAck;
        $f->exclusive = $exclusive;
        $f->nowait = $nowait;
        $f->arguments = $arguments;

        return $this->frameSend($f);
    }

    /**
     * send AMQP basic.cancel frame
     *
     * @param int $channel
     * @param string $consumerTag
     * @param bool $nowait
     * @return bool
     */
    public function basicCancel(int $channel, string $consumerTag, bool $nowait = false): bool
    {
        $f = new MethodBasicCancelFrame();
        $f->channel = $channel;
        $f->consumerTag = $consumerTag;
        $f->nowait = $nowait;

        return $this->frameSend($f);
    }

    /**
     * send AMQP basic.publish frame
     *
     * @param int $channel
     * @param string $exchange
     * @param array $headers
     * @param string $body
     * @param string $routingKey
     * @param bool $mandatory
     * @param bool $immediate
     * @return int|null
     */
    public function basicPublish(
        int $channel,
        string $exchange,
        array $headers = [],
        string $body = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false
    ): null|int {
        $f = new MethodBasicPublishFrame();
        $f->channel = $channel;
        $f->exchange = $exchange;
        $f->routingKey = $routingKey;
        $f->mandatory = $mandatory;
        $f->immediate = $immediate;
        if ($this->frameSend($f)) {
            $headerFrame = ContentHeaderFrame::fromArray($headers);
            $headerFrame->channel = $channel;
            $headerFrame->bodySize = strlen($body);
            if ($this->frameSend($headerFrame)) {
                while (($len = strlen($body)) > 0) {
                    $chunk = substr($body, 0, min($this->connection->getFrameMax(), $len));
                    if ($this->frameSend(new ContentBodyFrame($channel, strlen($chunk), $chunk))) {
                        $body = substr($body, strlen($chunk));
                    }
                }

                return $headerFrame->bodySize;
            }
        }

        return null;
    }

    /**
     * send AMQP basic.get frame
     *
     * @param int $channel
     * @param string $queue
     * @param bool $noAck
     * @return bool
     */
    public function basicGet(int $channel, string $queue = '', bool $noAck = false): bool
    {
        $f = new MethodBasicGetFrame();
        $f->channel = $channel;
        $f->queue = $queue;
        $f->noAck = $noAck;

        return $this->frameSend($f);
    }

    /**
     * send AMQP basic.ack frame
     *
     * @param int $channel
     * @param int $deliveryTag
     * @param bool $multiple
     * @return bool
     */
    public function basicAck(int $channel, int $deliveryTag, bool $multiple = false): bool
    {
        $f = new MethodBasicAckFrame();
        $f->channel = $channel;
        $f->deliveryTag = $deliveryTag;
        $f->multiple = $multiple;

        return $this->frameSend($f);
    }

    /**
     * send AMQP basic.reject frame
     *
     * @param int $channel
     * @param int $deliveryTag
     * @param bool $requeue
     * @return bool
     */
    public function basicReject(int $channel, int $deliveryTag, bool $requeue = false): bool
    {
        $f = new MethodBasicRejectFrame();
        $f->channel = $channel;
        $f->deliveryTag = $deliveryTag;
        $f->requeue = $requeue;

        return $this->frameSend($f);
    }

    /**
     * send AMQP basic.recover frame
     *
     * @param int $channel
     * @param bool $requeue
     * @return bool
     */
    public function basicRecover(int $channel, bool $requeue = false): bool
    {
        $f = new MethodBasicRecoverFrame();
        $f->channel = $channel;
        $f->requeue = $requeue;

        return $this->frameSend($f);
    }

    /**
     * send AMQP basic.nack frame
     *
     * @param int $channel
     * @param int $deliveryTag
     * @param bool $multiple
     * @param bool $requeue
     * @return bool
     */
    public function basicNack(int $channel, int $deliveryTag, bool $multiple = false, bool $requeue = false): bool
    {
        $f = new MethodBasicNackFrame();
        $f->channel = $channel;
        $f->deliveryTag = $deliveryTag;
        $f->multiple = $multiple;
        $f->requeue = $requeue;

        return $this->frameSend($f);
    }

    /**
     * send AMQP tx.select frame
     *
     * @param int $channel
     * @return bool
     */
    public function txSelect(int $channel): bool
    {
        $f = new MethodTxSelectFrame();
        $f->channel = $channel;

        return $this->frameSend($f);
    }

    /**
     * send AMQP tx.commit frame
     *
     * @param int $channel
     * @return bool
     */
    public function txCommit(int $channel): bool
    {
        $f = new MethodTxCommitFrame();
        $f->channel = $channel;

        return $this->frameSend($f);
    }

    /**
     * send AMQP tx.rollback frame
     *
     * @param int $channel
     * @return bool
     */
    public function txRollback(int $channel): bool
    {
        $f = new MethodTxRollbackFrame();
        $f->channel = $channel;

        return $this->frameSend($f);
    }

    /**
     * send AMQP confirm.select frame
     *
     * @param int $channel
     * @return bool
     */
    public function confirmSelect(int $channel): bool
    {
        $f = new MethodConfirmSelectFrame();
        $f->channel = $channel;

        return $this->frameSend($f);
    }
}
