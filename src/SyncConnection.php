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

    /** @var Closure|null  */
    protected ?Closure $_initCallback = null;

    /** @var Closure|null  */
    protected ?Closure $_errorCallback = null;

    /** @var Closure|null  */
    protected ?Closure $_finallyCallback = null;

    public function __construct(?array $config = null)
    {
        $this->_config = ($config !== null) ? $config : config('plugin.workbunny.webman-rabbitmq.app');
        $this->_errorCallback = $this->_config['error_callback'] ?? null;
        $this->_client = new SyncClient($this->_config);
    }

    /**
     * @return SyncClient
     */
    public function client(): SyncClient
    {
        if(!$this->_client instanceof SyncClient){
            $this->_client = new SyncClient($this->_config);
        }
        return $this->_client;
    }

    /**
     * @param Throwable|null $throwable
     * @return void
     */
    public function close(?Throwable $throwable = null): void
    {
        $replyCode = $throwable instanceof ClientException ? $throwable->getCode() : 0;
        $replyText = $throwable instanceof ClientException ? $throwable->getMessage() : '';
        try {
            if($this->_client instanceof SyncClient){
                if ($this->_client->isConnected()) {
                    $this->_client->disconnect($replyCode, $replyText)->done(function () {
                        $this->_client->stop();
                    });
                }
//                if ($this->_client->isConnected()) {
//                    $this->_client->run();
//                }
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
        if(!$this->client()->isConnected()){
            $this->client()->connect();
        }
        return $this->client();
    }

    /**
     * @param AbstractMessage $abstractMessage
     * @param bool $close
     * @return bool
     */
    public function publish(AbstractMessage $abstractMessage, bool $close = false): bool
    {
        if($this->_initCallback){
            call_user_func($this->_initCallback, null, $this);
        }
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
                call_user_func($this->_errorCallback, $throwable, $this);
            }
            return false;
        } finally {
            if($close or isset($throwable)){
                $this->close($throwable ?? null);
                $this->_setChannel();
            }
            if($this->_finallyCallback){
                call_user_func($this->_finallyCallback, $throwable ?? null, $this);
            }
        }
    }

    /**
     * @param bool $init
     * @return Channel|null
     * @throws Throwable
     */
    public function _getChannel(bool $init = true) : ?Channel
    {
        if(!$this->_channel instanceof Channel and $init){
            $this->_setChannel($this->connect()->channel());
        }
        return $this->_channel;
    }

    /**
     * @param Channel|null $channel
     * @return void
     */
    public function _setChannel(?Channel $channel = null){
        $this->_channel = $channel;
    }

    /**
     * @param Closure $callback
     * @return void
     */
    public function _setErrorCallback(Closure $callback)
    {
        $this->_errorCallback = $callback;
    }

    /**
     * @return Closure
     */
    public function _getErrorCallback(): Closure
    {
        return $this->_errorCallback;
    }

    /**
     * @param Closure $callback
     * @return void
     */
    public function _setFinallyCallback(Closure $callback)
    {
        $this->_finallyCallback = $callback;
    }

    /**
     * @return Closure
     */
    public function _getFinallyCallback(): Closure
    {
        return $this->_finallyCallback;
    }

    /**
     * @param Closure $callback
     * @return void
     */
    public function _setInitCallback(Closure $callback)
    {
        $this->_initCallback = $callback;
    }

    /**
     * @return Closure
     */
    public function _getInitCallback(): Closure
    {
        return $this->_initCallback;
    }
}