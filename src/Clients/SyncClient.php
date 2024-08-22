<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Clients;

use Bunny\Client;
use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use Exception;
use React\Promise\PromiseInterface;
use Throwable;
use Workbunny\WebmanRabbitMQ\Clients\Traits\ClientMethods;
use Workerman\Timer;

class SyncClient extends Client {

    use ClientMethods;

    /**
     * @var int|null 心跳定时器
     */
    protected ?int $heartbeatTimer = null;


    /** @inheritDoc */
    protected function authResponse(MethodConnectionStartFrame $start): PromiseInterface|bool
    {
        if (!str_contains($start->mechanisms, ($mechanism = $this->options['mechanism'] ?? 'AMQPLAIN'))) {
            throw new ClientException("Server does not support {$this->options['mechanism']} mechanism (supported: {$start->mechanisms}).");
        }

        if ($mechanism === 'PLAIN') {
            return $this->connectionStartOk([], $mechanism, sprintf("\0%s\0%s", $this->options["user"], $this->options["password"]), "en_US");
        } elseif($mechanism === 'AMQPLAIN') {
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
        try {
            if($this->heartbeatTimer){
                Timer::del($this->heartbeatTimer);
            }
            parent::__destruct();
        } catch (Throwable) {}
    }

    /**
     * @return SyncClient
     * @throws Exception
     */
    public function connect(): SyncClient
    {
        $result = parent::connect();
        $this->heartbeatTimer = Timer::add($this->options['heartbeat'] ?? 60, [$this, 'onHeartbeat']);
        return $result;
    }

    /** @inheritdoc  */
    public function disconnect($replyCode = 0, $replyText = ""): PromiseInterface
    {
        if ($this->heartbeatTimer) {
            Timer::del($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
        return parent::disconnect($replyCode, $replyText);
    }

    /**
     * Callback when heartbeat timer timed out.
     */
    public function onHeartbeat(): void
    {
        $this->writer->appendFrame(new HeartbeatFrame(), $this->writeBuffer);
        $this->flushWriteBuffer();

        if (is_callable($callback = $this->options['heartbeat_callback'] ?? null)) {
            call_user_func($callback, $this);
        }
    }
}