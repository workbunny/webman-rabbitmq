<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Clients;

use Bunny\ClientStateEnum;
use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\MethodConnectionCloseFrame;
use Bunny\Protocol\MethodConnectionCloseOkFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use React\Promise\PromiseInterface;
use Workbunny\WebmanRabbitMQ\Clients\Traits\ClientMethods;
use Workerman\RabbitMQ\Client;
use Workerman\Timer;
use Workerman\Worker;

class AsyncClient extends Client
{
    use ClientMethods;

    public static ?bool $sync = false;


    /** @inheritDoc */
    protected function authResponse(MethodConnectionStartFrame $start): PromiseInterface|bool
    {
        if (!str_contains($start->mechanisms, ($mechanism = $this->options['mechanism'] ?? 'AMQPLAIN'))) {
            throw new ClientException("Server does not support {$this->options['mechanism']} mechanism (supported: {$start->mechanisms}).");
        }

        if ($mechanism === 'PLAIN') {
            return $this->connectionStartOk([], $mechanism, sprintf("\0%s\0%s", $this->options["user"], $this->options["password"]), "en_US");
        } elseif ($mechanism === 'AMQPLAIN') {

            $responseBuffer = new Buffer();
            $this->writer->appendTable([
                "LOGIN" => $this->options["user"],
                "PASSWORD" => $this->options["password"],
            ], $responseBuffer);

            $responseBuffer->discard(4);

            return $this->connectionStartOk([], $mechanism, $responseBuffer->read($responseBuffer->getLength()), "en_US");
        } else {
            throw new ClientException("Client does not support {$mechanism} mechanism. ");
        }
    }

    /** @inheritDoc */
    public function __destruct()
    {
        if (self::$sync) {
            if($this->isConnected()){
                $this->syncDisconnect();
            }
        } else {
            parent::__destruct();
        }
    }

    /** @inheritdoc  */
    public function disconnect($replyCode = 0, $replyText = ""): PromiseInterface
    {
        if ($this->heartbeatTimer and is_int($this->heartbeatTimer)) {
            Timer::del($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
        return parent::disconnect($replyCode, $replyText);
    }

    /**
     * @param int|string $replyCode
     * @param int|string $replyText
     * @return void
     */
    public function syncDisconnect(int|string $replyCode = 0, int|string $replyText = ""): void
    {
        if ($this->state !== ClientStateEnum::CONNECTED) {
            throw new ClientException("Client is not connected.");
        }

        $this->state = ClientStateEnum::DISCONNECTING;

        if ($replyCode === 0) {
            foreach ($this->channels as $channel) {
                $this->syncChannelClose($channel->getChannelId(), $replyCode, $replyText, 0, 0);
                unset($this->channels[$channel->getChannelId()]);
            }
        }
        else{
            foreach($this->channels as $channel){
                unset($this->channels[$channel->getChannelId()]);
            }
        }

        if ($this->heartbeatTimer and is_int($this->heartbeatTimer)) {
            Timer::del($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }

        $this->syncConnectionClose($replyCode, $replyText, 0, 0);
        $this->closeStream();
        $this->init();
        if($replyCode !== 0){
            Worker::stopAll(0,"RabbitMQ client disconnected: [$replyCode] $replyText");
        }
    }

    /**
     * @param $channel
     * @param $replyCode
     * @param $replyText
     * @param $closeClassId
     * @param $closeMethodId
     * @return bool
     */
    public function syncChannelClose($channel, $replyCode, $replyText, $closeClassId, $closeMethodId): bool
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(11 + strlen($replyText));
        $buffer->appendUint16(20);
        $buffer->appendUint16(40);
        $buffer->appendInt16($replyCode);
        $buffer->appendUint8(strlen($replyText)); $buffer->append($replyText);
        $buffer->appendInt16($closeClassId);
        $buffer->appendInt16($closeMethodId);
        $buffer->appendUint8(206);
        return $this->syncFlushWriteBuffer();
    }

    /**
     * @param $replyCode
     * @param $replyText
     * @param $closeClassId
     * @param $closeMethodId
     * @return bool|MethodConnectionCloseOkFrame
     */
    public function syncConnectionClose($replyCode, $replyText, $closeClassId, $closeMethodId): bool|MethodConnectionCloseOkFrame
    {
        $buffer = $this->getWriteBuffer();
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(11 + strlen($replyText));
        $buffer->appendUint16(10);
        $buffer->appendUint16(50);
        $buffer->appendInt16($replyCode);
        $buffer->appendUint8(strlen($replyText)); $buffer->append($replyText);
        $buffer->appendInt16($closeClassId);
        $buffer->appendInt16($closeMethodId);
        $buffer->appendUint8(206);
        $this->syncFlushWriteBuffer();
        for (;;) {
            while (($frame = $this->getReader()->consumeFrame($this->getReadBuffer())) === null) {
                $this->read();
            }
            if ($frame instanceof MethodConnectionCloseOkFrame) {
                return $frame;
            } elseif ($frame instanceof MethodConnectionCloseFrame) {
                $buffer = $this->getWriteBuffer();
                $buffer->appendUint8(1);
                $buffer->appendUint16(0);
                $buffer->appendUint32(4);
                $buffer->appendUint16(10);
                $buffer->appendUint16(51);
                $buffer->appendUint8(206);
                return $this->syncFlushWriteBuffer();
            } else {
                $this->enqueue($frame);
            }
        }
    }

    /**
     * @return true
     */
    protected function syncFlushWriteBuffer(): bool
    {
        while (!$this->writeBuffer->isEmpty()) {
            $this->write();
        }
        return true;
    }
}
