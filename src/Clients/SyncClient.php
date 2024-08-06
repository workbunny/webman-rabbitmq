<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Clients;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\HeartbeatFrame;
use Bunny\Protocol\MethodConnectionStartFrame;
use Exception;
use React\Promise\PromiseInterface;
use Throwable;
use Workerman\Timer;

class SyncClient extends Client {

    protected ?int $heartbeatTimer = null;

    /**
     * @return Channel[]
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * 重写authResponse方法以支持PLAIN及AMQPLAIN两种机制
     * @param MethodConnectionStartFrame $start
     * @return bool|PromiseInterface
     */
    protected function authResponse(MethodConnectionStartFrame $start)
    {
        if (!str_contains($start->mechanisms, ($mechanism = $this->options['mechanism'] ?? 'AMQPLAIN'))) {
            throw new ClientException("Server does not support {$this->options['mechanism']} mechanism (supported: {$start->mechanisms}).");
        }

        if($mechanism === 'PLAIN'){
            return $this->connectionStartOk([], $mechanism, sprintf("\0%s\0%s", $this->options["user"], $this->options["password"]), "en_US");
        }elseif($mechanism === 'AMQPLAIN'){

            $responseBuffer = new Buffer();
            $this->writer->appendTable([
                "LOGIN" => $this->options["user"],
                "PASSWORD" => $this->options["password"],
            ], $responseBuffer);

            $responseBuffer->discard(4);

            return $this->connectionStartOk([], $mechanism, $responseBuffer->read($responseBuffer->getLength()), "en_US");
        }else{

            throw new ClientException("Client does not support {$mechanism} mechanism. ");
        }
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

    public function __destruct()
    {
        try {
            if($this->heartbeatTimer){
                Timer::del($this->heartbeatTimer);
            }
            parent::__destruct();
        }catch (Throwable $throwable){}
    }

    /**
     * Callback when heartbeat timer timed out.
     */
    public function onHeartbeat()
    {
        $this->writer->appendFrame(new HeartbeatFrame(), $this->writeBuffer);
        $this->flushWriteBuffer();

        if (is_callable($this->options['heartbeat_callback'] ?? null)) {
            $this->options['heartbeat_callback']->call($this);
        }
    }
}