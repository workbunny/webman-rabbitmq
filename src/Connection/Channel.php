<?php

declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanRabbitMQ\Connection;

use Bunny\ChannelModeEnum;
use Bunny\ChannelStateEnum;
use Bunny\Constants;
use Bunny\Message;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicCancelOkFrame;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicDeliverFrame;
use Bunny\Protocol\MethodBasicGetEmptyFrame;
use Bunny\Protocol\MethodBasicGetOkFrame;
use Bunny\Protocol\MethodBasicNackFrame;
use Bunny\Protocol\MethodBasicReturnFrame;
use Bunny\Protocol\MethodChannelCloseFrame;
use Bunny\Protocol\MethodChannelCloseOkFrame;
use Bunny\Protocol\MethodTxCommitOkFrame;
use Bunny\Protocol\MethodTxRollbackOkFrame;
use Bunny\Protocol\MethodTxSelectOkFrame;
use Closure;
use Workbunny\WebmanRabbitMQ\Connection\Traits\ChannelsMethods;
use Workbunny\WebmanRabbitMQ\Exceptions\WebmanRabbitMQException;

class Channel
{
    use ChannelsMethods;

    /** @var int current channel id */
    protected int $id = Constants::CONNECTION_CHANNEL;

    /** @var int current channel state */
    protected int $state = ChannelStateEnum::READY;

    /** @var int current channel mode */
    protected int $mode = ChannelModeEnum::REGULAR;

    /** @var Connection current connection object */
    protected Connection $connection;

    /** @var array<string, Closure> deliver callbacks */
    protected array $deliverCallbacks = [];

    /** @var array<int, Closure> get callbacks */
    protected array $getCallbacks = [];

    /** @var Closure|null return callback */
    protected Closure|null $returnCallback = null;

    /** @var MethodBasicDeliverFrame|MethodBasicReturnFrame|MethodBasicGetOkFrame|null current deliver frame or return frame */
    protected null|MethodBasicDeliverFrame|MethodBasicReturnFrame|MethodBasicGetOkFrame $currentContentStartFrame = null;

    /** @var ContentHeaderFrame|null current content header frame */
    protected ?ContentHeaderFrame $currentContentHeaderFrame = null;

    /** @var Buffer current content body buffer */
    protected Buffer $currentContentBodyBuffer;

    /**
     * @param Connection $connection
     * @param int $channelId
     */
    public function __construct(Connection $connection, int $channelId)
    {
        $this->connection = $connection;
        $this->id = $channelId;
        $this->currentContentBodyBuffer = new Buffer();
    }

    /**
     * channel id
     *
     * @return int
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * get channel state
     *
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * set channel state
     *
     * @param int $state
     * @return void
     */
    public function setState(int $state): void
    {
        $this->state = $state;
    }

    /**
     * get channel mode
     *
     * @return int
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * set channel mode
     *
     * @param int $mode
     * @return void
     */
    public function setMode(int $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * connection send frame
     *  - channel only send frame, not buffer
     *
     * @param AbstractFrame $frame
     * @return bool
     */
    public function frameSend(AbstractFrame $frame): bool
    {
        return $this->connection->frameSend($frame);
    }

    /**
     * register return callback
     *
     * @param Closure $callback
     * @return $this
     */
    public function returnCallbackRegister(Closure $callback): static
    {
        $this->returnCallback = $callback;
        return $this;
    }

    /**
     * unregister return callback
     *
     * @return $this
     */
    public function returnCallbackUnregister(): static
    {
        $this->returnCallback = null;
        return $this;
    }

    /**
     * ack send
     *
     * @param Message $message
     * @param bool $multiple
     * @return bool
     */
    public function ack(Message $message, bool $multiple = false): bool
    {
        return $this->basicAck($this->id(), $message->deliveryTag, $multiple);
    }

    /**
     * Nack send
     *
     * @param Message $message
     * @param boolean $multiple
     * @param boolean $requeue
     * @return bool
     */
    public function nack(Message $message, bool $multiple = false, bool $requeue = true): bool
    {
        return $this->basicNack($this->id(), $message->deliveryTag, $multiple, $requeue);
    }

    /**
     * reject send
     *
     * @param Message $message
     * @param bool $requeue
     * @return bool
     */
    public function reject(Message $message, bool $requeue = true): bool
    {
        return $this->basicReject($this->id(), $message->deliveryTag, $requeue);
    }

    /**
     * publish message
     *
     * @param string $exchange
     * @param array $headers
     * @param array|string $body
     * @param string $routingKey
     * @param bool $mandatory
     * @param bool $immediate
     * @return int|null
     */
    public function publish(
        string $exchange, array $headers = [], array|string $body = '', string $routingKey = '',
        bool   $mandatory = false, bool $immediate = false
    ): null|int
    {
        return $this->basicPublish($this->id(), $exchange, $headers, $body, $routingKey, $mandatory, $immediate);
    }

    /**
     * get message
     *
     * @param Closure $callback = function(Message $message, Channel $channel, Connection $connection) {}
     * @param string $queue
     * @param bool $noAck
     * @param bool $nowait when true, return null, non-blocking; when false, return MethodBasicGetOkFrame|MethodBasicGetEmptyFrame, blocking
     * @return void
     */
    public function get(Closure $callback, string $queue = '', bool $noAck = false, bool $nowait = false): void
    {
        $this->basicGet($this->id(), $queue, $noAck);
        $this->getCallbacks[] = $callback;
        if (!$nowait) {
            $id = spl_object_id($callback);
            /** @var MethodBasicGetEmptyFrame|MethodBasicGetOkFrame $frame */
            $this->connection->await("basic.get.$id");
        }
    }

    /**
     * consume message
     *
     * @param Closure $callback = function(Message $message, Channel $channel, Connection $connection) {}
     * @param string $queue
     * @param string $consumerTag
     * @param bool $noLocal
     * @param bool $noAck
     * @param bool $exclusive
     * @param bool $nowait
     * @param array $arguments
     * @return MethodBasicConsumeOkFrame
     */
    public function consume(Closure $callback,
                            string   $queue = '', string $consumerTag = '', bool $noLocal = false, bool $noAck = false,
                            bool     $exclusive = false, bool $nowait = false, array $arguments = []
    ): MethodBasicConsumeOkFrame
    {
        $this->basicConsume($this->id(), $queue, $consumerTag, $noLocal, $noAck, $exclusive, $nowait, $arguments);
        /** @var MethodBasicConsumeOkFrame $frame */
        $frame = $this->connection->await(MethodBasicConsumeOkFrame::class);
        $this->deliverCallbacks[$frame->consumerTag] = $callback;
        return $frame;
    }

    /**
     * @param string $consumerTag
     * @param bool $nowait
     * @return bool|MethodBasicCancelOkFrame
     */
    public function cancel(string $consumerTag, bool $nowait = false): bool|MethodBasicCancelOkFrame
    {
        $res = $this->basicCancel($this->id(), $consumerTag, $nowait);
        if (!$nowait) {
            /** @var MethodBasicCancelOkFrame $res */
            $res = $this->connection->await(MethodBasicCancelOkFrame::class);
        }
        unset($this->deliverCallbacks[$consumerTag]);
        return $res;
    }

    /**
     * current channel close
     *
     * @param int $replyCode
     * @param string $replyText
     * @param bool $nowait
     * @return MethodChannelCloseOkFrame|bool|null
     */
    public function close(int $replyCode = 0, string $replyText = '', bool $nowait = false): MethodChannelCloseOkFrame|bool|null
    {
        if (in_array($this->getState(), [ChannelStateEnum::CLOSING, ChannelStateEnum::CLOSED])) {
            return null;
        }
        $f = new MethodChannelCloseFrame();
        $f->channel = $this->id;
        $f->replyCode = $replyCode;
        $f->replyText = $replyText;
        $f->closeClassId = 0;
        $f->closeMethodId = 0;
        $res = $this->frameSend($f);
        if (!$nowait and $res) {
            $res = $this->connection->await(MethodChannelCloseOkFrame::class, function (MethodChannelCloseFrame $frame) {
                return $frame->channel === $this->id;
            });
        }
        $this->setState(ChannelStateEnum::CLOSED);
        $this->connection->channels()->closeConnection($this);
        return $res;
    }

    /**
     * start transactional mode
     *
     * @return MethodTxSelectOkFrame
     */
    public function select(): MethodTxSelectOkFrame
    {
        if ($this->getMode() !== ChannelModeEnum::REGULAR) {
            throw new WebmanRabbitMQException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $this->txSelect($this->id());
        /** @var MethodTxSelectOkFrame $frame */
        $frame = $this->connection->await(MethodTxSelectOkFrame::class);
        $this->setMode(ChannelModeEnum::TRANSACTIONAL);
        return $frame;
    }

    /**
     * commit transaction
     *
     * @return MethodTxCommitOkFrame
     */
    public function commit(): MethodTxCommitOkFrame
    {
        if ($this->getMode() !== ChannelModeEnum::TRANSACTIONAL) {
            throw new WebmanRabbitMQException("Channel not in transactional mode, cannot call 'tx.commit'.");
        }
        $this->txCommit($this->id());
        /** @var MethodTxCommitOkFrame $res */
        $res = $this->connection->await(MethodTxCommitOkFrame::class);

        return $res;
    }

    /**
     * rollback transaction
     *
     * @return MethodTxRollbackOkFrame
     */
    public function rollback(): MethodTxRollbackOkFrame
    {
        if ($this->getMode() !== ChannelModeEnum::TRANSACTIONAL) {
            throw new WebmanRabbitMQException("Channel not in transactional mode, cannot call 'tx.rollback'.");
        }
        $this->txRollback($this->id());
        /** @var MethodTxRollbackOkFrame $res */
        $res = $this->connection->await(MethodTxRollbackOkFrame::class);
        return $res;
    }

    /**
     * confirm mode
     *
     * @param Closure $action
     * @param bool $nowait
     * @return MethodBasicAckFrame|MethodBasicNackFrame
     */
    public function confirm(Closure $action, bool $nowait = false): MethodBasicAckFrame|MethodBasicNackFrame
    {
        if ($this->mode !== ChannelModeEnum::REGULAR) {
            throw new WebmanRabbitMQException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $this->confirmSelect($this->id());
        if (!$nowait) {
            $this->connection->await(MethodTxSelectOkFrame::class);
        }
        $action();
        /** @var MethodBasicAckFrame|MethodBasicNackFrame $frame */
        $frame = $this->connection->await('confirm.select');
        return $frame;
    }

    /**
     * on frame received
     *
     * @param AbstractFrame $frame
     * @return void
     */
    public function onFrameReceived(AbstractFrame $frame): void
    {
        // ignore it, when the channel is error or closed
        if (in_array($this->getState(), [ChannelStateEnum::ERROR, ChannelStateEnum::CLOSED])) {
            return;
        }
        // closing or closed
        if (($frame instanceof MethodChannelCloseFrame) or ($frame instanceof MethodChannelCloseOkFrame)) {
            $this->connection->channels()->closeConnection($this);
            return;
        }
        // wait deliver / get / return
        if (
            $this->state === ChannelStateEnum::READY and
            (
                $frame instanceof MethodBasicDeliverFrame or
                $frame instanceof MethodBasicReturnFrame or
                $frame instanceof MethodBasicGetOkFrame
            )
        ) {
            $this->currentContentStartFrame = $frame;
            $this->currentContentHeaderFrame = null;
            $this->currentContentBodyBuffer->discard($this->currentContentBodyBuffer->getLength());
            $this->setState(ChannelStateEnum::AWAITING_HEADER);
            return;
        }
        // wait header
        if ($this->state === ChannelStateEnum::AWAITING_HEADER and $frame instanceof ContentHeaderFrame) {
            $this->currentContentHeaderFrame = $frame;
            $this->currentContentBodyBuffer->discard($this->currentContentBodyBuffer->getLength());
            if ($this->currentContentHeaderFrame->bodySize > 0) {
                $this->setState(ChannelStateEnum::AWAITING_BODY);
            }

            return;
        }
        // wait body frames
        if ($this->state === ChannelStateEnum::AWAITING_BODY and $frame instanceof ContentBodyFrame) {
            $this->currentContentBodyBuffer->append($frame->payload);
            if ($frame->payloadSize + $this->currentContentBodyBuffer->getLength() >= $this->currentContentHeaderFrame->bodySize) {
                $this->setState(ChannelStateEnum::READY);
                $this->onBodyFramesComplete();
            }
            return;
        }
        // confirm recv - ack or nack
        if ($frame instanceof MethodBasicAckFrame or $frame instanceof MethodBasicNackFrame) {
            $this->connection->wakeup('confirm.select', $frame);
        }
    }

    /**
     * body frames complete
     *
     * @return void
     */
    protected function onBodyFramesComplete(): void
    {
        $content = $this->currentContentBodyBuffer->consume($this->currentContentBodyBuffer->getLength());
        switch (true) {
            // get
            case $this->currentContentStartFrame instanceof MethodBasicGetOkFrame:
                /** @var MethodBasicGetOkFrame $frame */
                $frame = $this->currentContentStartFrame;
                if ($callback = array_shift($this->getCallbacks)) {
                    $message = new Message(
                        $frame->consumerTag,
                        $frame->deliveryTag,
                        $frame->redelivered,
                        $frame->exchange,
                        $frame->routingKey,
                        $this->currentContentHeaderFrame->toArray(),
                        $content
                    );
                    $id = spl_object_id($callback);
                    $callback($message, $this, $this->connection);
                    $this->connection->wakeup("basic.get.$id", true);
                }
                break;
            // deliver
            case $this->currentContentStartFrame instanceof MethodBasicDeliverFrame:
                /** @var MethodBasicDeliverFrame $frame */
                $frame = $this->currentContentStartFrame;
                if ($callback = ($this->deliverCallbacks[$frame->consumerTag] ?? null)) {
                    $message = new Message(
                        $frame->consumerTag,
                        $frame->deliveryTag,
                        $frame->redelivered,
                        $frame->exchange,
                        $frame->routingKey,
                        $this->currentContentHeaderFrame->toArray(),
                        $content
                    );
                    $callback($message, $this, $this->connection);
                }
                break;
            // return
            case $this->currentContentStartFrame instanceof MethodBasicReturnFrame:
                /** @var MethodBasicReturnFrame $frame */
                $frame = $this->currentContentStartFrame;
                if ($this->returnCallback) {
                    $message = new Message(
                        null,
                        null,
                        false,
                        $frame->exchange,
                        $frame->routingKey,
                        $this->currentContentHeaderFrame->toArray(),
                        $content
                    );
                    ($this->returnCallback)($message, $this, $this->connection);
                }
                break;
            default:
                break;
        }
        $this->currentContentStartFrame = null;
        $this->currentContentHeaderFrame = null;
    }

}
