<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Bunny\Client;
use Bunny\Exception\ClientException;
use Bunny\Protocol\Buffer;
use Bunny\Protocol\MethodConnectionStartFrame;
use React\Promise\PromiseInterface;
use Throwable;

class SyncClient extends Client {

    /**
     * 重写authResponse方法以支持PLAIN及AMQPLAIN两种机制
     * @param MethodConnectionStartFrame $start
     * @return bool|PromiseInterface
     */
    protected function authResponse(MethodConnectionStartFrame $start)
    {
        if (strpos($start->mechanisms, ($mechanism = $this->options['mechanism'] ?? 'AMQPLAIN')) === false) {
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

    public function __destruct()
    {
        try {
            parent::__destruct();
        }catch (Throwable $throwable){}
    }
}