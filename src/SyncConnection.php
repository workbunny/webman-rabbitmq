<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Bunny\Channel;
use Bunny\Exception\ClientException;
use Closure;
use Throwable;
use Workbunny\WebmanRabbitMQ\Protocols\AbstractMessage;

class SyncConnection
{
    /**
     * @var Channel|null
     */
    protected ?Channel $_channel = null;

    /**
     * @var SyncClient|null
     */
    protected ?SyncClient $_client = null;

    /**
     * @var array|mixed
     */
    protected array $_config = [];

    /**
     * @var Closure|mixed|null
     */
    protected ?Closure $_errorCallback = null;

    public function __construct(array $config = [])
    {
        $this->_config = $config ?? config('plugin.workbunny.webman-rabbitmq.app');
        $this->_client = new SyncClient($this->_config);
        $this->_errorCallback = $this->_config['error_callback'] ?? null;
    }

    public function client(): SyncClient
    {
        if(!$this->_client instanceof SyncClient){
            $this->_client = new SyncClient($this->_config);
        }
        return $this->_client;
    }

    public function close(): void
    {
        try {
            if($this->_client instanceof SyncClient){
                if ($this->_client->isConnected()) {
                    $this->_client->disconnect()->done(function () {
                        $this->_client->stop();
                    });
                }
                if ($this->_client->isConnected()) {
                    $this->_client->run();
                }
            }
        }catch (Throwable $throwable){} finally {
            $this->_client = null;
        }
    }

    /**
     * @return SyncClient
     * @throws Throwable
     */
    public function connect() : SyncClient
    {
        try {
            if(!$this->client()->isConnected()){
                $this->client()->connect();
            }
        }catch (ClientException $exception){
            $this->_client = null;
            $this->client()->connect();
        }catch (Throwable $throwable){
            throw $throwable;
        }finally {
            return $this->_client;
        }
    }

    /**
     * @param AbstractMessage $abstractMessage
     * @param bool $close
     * @return bool
     */
    public function publish(AbstractMessage $abstractMessage, bool $close = false): bool
    {
        try {
            $this->_getChannel()->exchangeDeclare(
                $abstractMessage->getExchange(),
                $abstractMessage->getExchangeType(),
                $abstractMessage->isPassive(),
                $abstractMessage->isDurable(),
                $abstractMessage->isAutoDelete(),
                $abstractMessage->isInternal(),
                $abstractMessage->isNowait(),
                $abstractMessage->getArguments()
            );
            $this->_getChannel()->queueDeclare(
                $abstractMessage->getQueue(),
                $abstractMessage->isPassive(),
                $abstractMessage->isDurable(),
                $abstractMessage->isExclusive(),
                $abstractMessage->isAutoDelete(),
                $abstractMessage->isNowait(),
                $abstractMessage->getArguments()
            );
            $this->_getChannel()->queueBind(
                $abstractMessage->getQueue(),
                $abstractMessage->getExchange(),
                $abstractMessage->getRoutingKey(),
                $abstractMessage->isNowait(),
                $abstractMessage->getArguments()
            );
            return (bool)$this->_getChannel()->publish(
                $abstractMessage->getBody(),
                $abstractMessage->getHeaders(),
                $abstractMessage->getExchange(),
                $abstractMessage->getRoutingKey(),
                $abstractMessage->isMandatory(),
                $abstractMessage->isImmediate()
            );
        }catch (Throwable $throwable){
            if($this->_errorCallback){
                ($this->_errorCallback)($throwable);
            }
            return false;
        } finally {
            if($close){
                $this->_setChannel();
                $this->close();
            }
        }
    }

    /**
     * @return Channel|null
     * @throws Throwable
     */
    protected function _getChannel() : ?Channel
    {
        if(!$this->_channel instanceof Channel){
            $this->_setChannel($this->connect()->channel());
        }
        return $this->_channel;
    }

    protected function _setChannel(?Channel $channel = null){
        $this->_channel = $channel;
    }
}