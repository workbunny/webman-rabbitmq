<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Exception\ClientException;
use Bunny\Message;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Closure;
use React\Promise\PromiseInterface;
use Throwable;
use Workbunny\WebmanRabbitMQ\Protocols\AbstractMessage;

class Connection
{
    /**
     * @var AsyncClient|null
     */
    protected ?AsyncClient $_client = null;

    /**
     * @var Channel|null
     */
    protected ?Channel $_channel = null;

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
        $this->_client = new AsyncClient($this->_config);
    }

    /**
     * @param bool $sync
     * @param Throwable|null $throwable
     * @return void
     */
    public function close(bool $sync = false, ?Throwable $throwable = null): void
    {
        $replyCode = $throwable instanceof ClientException ? $throwable->getCode() : 0;
        $replyText = $throwable instanceof ClientException ? $throwable->getMessage() : '';
        try {
            if($this->_client instanceof AsyncClient){
                if ($this->_client->isConnected()) {
                    if(!($this->_client::$sync = $sync)){
                        $this->_client->disconnect($replyCode, $replyText);
                    }else{
                        $this->_client->syncDisconnect($replyCode, $replyText);
                    }
                }
            }
        }catch (Throwable $throwable){} finally {
            $this->_client = null;
        }
    }

    /**
     * @return AsyncClient
     */
    public function client(): AsyncClient
    {
        if(!$this->_client instanceof AsyncClient){
            $this->_client = new AsyncClient($this->_config);
        }
        return $this->_client;
    }

    /**
     * 消费
     * @param AbstractMessage $abstractMessage
     * @return void
     */
    public function consume(AbstractMessage $abstractMessage) : void
    {
        if($this->_initCallback){
            call_user_func($this->_initCallback, null, $this);
        }
        $this->client()->connect()->then(
            function (AsyncClient $client){
                return $client->channel()->then(function (Channel $channel){
                    return $channel;
                },function (Throwable $throwable){
                    if($this->_errorCallback){
                        call_user_func($this->_errorCallback, $throwable, $this);
                    }
                    $this->close(true, $throwable);
                });
            },
            function (Throwable $throwable){
                if($this->_errorCallback){
                    call_user_func($this->_errorCallback, $throwable, $this);
                }
                $this->close(true, $throwable);
            }
        )->then(function (Channel $channel) use ($abstractMessage) {
            return $channel->exchangeDeclare(
                $abstractMessage->getExchange(),
                $abstractMessage->getExchangeType(),
                $abstractMessage->isPassive(),
                $abstractMessage->isDurable(),
                $abstractMessage->isAutoDelete(),
                $abstractMessage->isInternal(),
                $abstractMessage->isNowait(),
                $abstractMessage->getArguments()
            )->then(
                function () use ($channel) {
                    return $channel;
                },
                function (Throwable $throwable){
                    if($this->_errorCallback){
                        call_user_func($this->_errorCallback, $throwable, $this);
                    }
                    $this->close(true, $throwable);
                }
            );
        })->then(function (Channel $channel) use ($abstractMessage) {
            return $channel->queueDeclare(
                $abstractMessage->getQueue(),
                $abstractMessage->isPassive(),
                $abstractMessage->isDurable(),
                $abstractMessage->isExclusive(),
                $abstractMessage->isAutoDelete(),
                $abstractMessage->isNowait(),
                $abstractMessage->getArguments()
            )->then(
                function () use ($channel) {
                    return $channel;
                },
                function (Throwable $throwable){
                    if($this->_errorCallback){
                        call_user_func($this->_errorCallback, $throwable, $this);
                    }
                    $this->close(true, $throwable);
                }
            );
        })->then(function (Channel $channel) use ($abstractMessage) {
            return $channel->queueBind(
                $abstractMessage->getQueue(),
                $abstractMessage->getExchange(),
                $abstractMessage->getRoutingKey(),
                $abstractMessage->isNowait(),
                $abstractMessage->getArguments()
            )->then(
                function () use ($channel) {
                    return $channel;
                },
                function (Throwable $throwable){
                    if($this->_errorCallback){
                        call_user_func($this->_errorCallback, $throwable, $this);
                    }
                    $this->close(true, $throwable);
                }
            );
        })->then(function (Channel $channel) use ($abstractMessage) {
            return $channel->qos(
                $abstractMessage->getPrefetchSize(),
                $abstractMessage->getPrefetchCount(),
                $abstractMessage->isGlobal()
            )->then(
                function () use ($channel) {
                    return $channel;
                },
                function (Throwable $throwable){
                    if($this->_errorCallback){
                        call_user_func($this->_errorCallback, $throwable, $this);
                    }
                    $this->close(true, $throwable);
                }
            );
        })->then(function (Channel $channel) use ($abstractMessage) {
            //Waiting for messages
            $channel->consume(
                function (Message $message, Channel $channel, Client $client) use ($abstractMessage) {
                    try {
                        $tag = ($abstractMessage->getCallback())($message, $channel, $client);
                    }catch (Throwable $throwable){
                        $tag = null;
                    }
                    switch (true) {
                        case $tag === Constants::ACK:
                            $res = $channel->ack($message);
                            break;
                        case $tag === Constants::NACK:
                            $res = $channel->nack($message);
                            break;
                        case $tag === Constants::REQUEUE:
                        default:
                            $res = $channel->reject($message);
                            break;
                    }
                    $res->then(
                        function (){},
                        function (Throwable $throwable){
                            if($this->_errorCallback){
                                call_user_func($this->_errorCallback, $throwable, $this);
                            }
                            $this->close(true, $throwable);
                        }
                    );
            },
                $abstractMessage->getQueue(),
                $abstractMessage->getConsumerTag(),
                $abstractMessage->isNoLocal(),
                $abstractMessage->isNoAck(),
                $abstractMessage->isExclusive(),
                $abstractMessage->isNowait(),
                $abstractMessage->getArguments()
            )->then(
                function (MethodBasicConsumeOkFrame $ok){

                },
                function (Throwable $throwable){
                    if($this->_errorCallback){
                        call_user_func($this->_errorCallback, $throwable, $this);
                    }
                    $this->close(true, $throwable);
                }
            );
        });
    }

    /**
     * 发布
     * @param AbstractMessage $abstractMessage
     * @param bool $close
     * @return bool|PromiseInterface
     */
    public function publish(AbstractMessage $abstractMessage, bool $close = false) : PromiseInterface
    {
        // 如果存在连接
        if($this->client()->isConnected() and $this->_getChannel()){
            return $this->_getChannel()->publish(
                $abstractMessage->getBody(),
                $abstractMessage->getHeaders(),
                $abstractMessage->getExchange(),
                $abstractMessage->getRoutingKey(),
                $abstractMessage->isMandatory(),
                $abstractMessage->isImmediate()
            )->then(
                function () use ($close){
                    if($close){
                        $this->close();
                        $this->_setChannel();
                    }
                    return true;
                },
                function (Throwable $throwable){
                    if($this->_errorCallback){
                        call_user_func($this->_errorCallback, $throwable, $this);
                    }
                    $this->close(true, $throwable);
                    $this->_setChannel();
                    return false;
                }
            );
        }
        // 如果不存在连接
        else {
            return $this->client()->connect()->then(function (AsyncClient $client){
                return $client->channel()->then(function (Channel $channel){
                    $this->_setChannel($channel);
                    return $channel;
                },function (Throwable $throwable){
                    if($this->_errorCallback){
                        call_user_func($this->_errorCallback, $throwable, $this);
                    }
                    $this->close(true, $throwable);
                    $this->_setChannel();
                    return false;
                });
            },function (Throwable $throwable){
                if($this->_errorCallback){
                    call_user_func($this->_errorCallback, $throwable, $this);
                }
                $this->close(true, $throwable);
                $this->_setChannel();
            })->then(function (Channel $channel) use ($abstractMessage) {
                return $channel->exchangeDeclare(
                    $abstractMessage->getExchange(),
                    $abstractMessage->getExchangeType(),
                    $abstractMessage->isPassive(),
                    $abstractMessage->isDurable(),
                    $abstractMessage->isAutoDelete(),
                    $abstractMessage->isInternal(),
                    $abstractMessage->isNowait(),
                    $abstractMessage->getArguments()
                )->then(
                    function () use ($channel) {
                        return $channel;
                    },
                    function (Throwable $throwable){
                        if($this->_errorCallback){
                            call_user_func($this->_errorCallback, $throwable, $this);
                        }
                        $this->close(true, $throwable);
                        $this->_setChannel();
                        return false;
                    }
                );
            })->then(function (Channel $channel) use ($abstractMessage) {
                return $channel->queueDeclare(
                    $abstractMessage->getQueue(),
                    $abstractMessage->isPassive(),
                    $abstractMessage->isDurable(),
                    $abstractMessage->isExclusive(),
                    $abstractMessage->isAutoDelete(),
                    $abstractMessage->isNowait(),
                    $abstractMessage->getArguments()
                )->then(
                    function () use ($channel) {
                        return $channel;
                    },
                    function (Throwable $throwable){
                        if($this->_errorCallback){
                            call_user_func($this->_errorCallback, $throwable, $this);
                        }
                        $this->close(true, $throwable);
                        $this->_setChannel();
                        return false;
                    }
                );
            })->then(function (Channel $channel) use ($abstractMessage) {
                return $channel->queueBind(
                    $abstractMessage->getQueue(),
                    $abstractMessage->getExchange(),
                    $abstractMessage->getRoutingKey(),
                    $abstractMessage->isNowait(),
                    $abstractMessage->getArguments()
                )->then(
                    function () use ($channel) {
                        return $channel;
                    },
                    function (Throwable $throwable){
                        if($this->_errorCallback){
                            call_user_func($this->_errorCallback, $throwable, $this);
                        }
                        $this->close(true, $throwable);
                        $this->_setChannel();
                        return false;
                    }
                );
            })->then(function (Channel $channel) use ($abstractMessage, $close) {
                return $channel->publish(
                    $abstractMessage->getBody(),
                    $abstractMessage->getHeaders(),
                    $abstractMessage->getExchange(),
                    $abstractMessage->getRoutingKey(),
                    $abstractMessage->isMandatory(),
                    $abstractMessage->isImmediate()
                )->then(
                    function () use ($close){
                        if($close){
                            $this->close();
                            $this->_setChannel();
                        }
                        return true;
                    },
                    function (Throwable $throwable){
                        if($this->_errorCallback){
                            call_user_func($this->_errorCallback, $throwable, $this);
                        }
                        $this->close(true, $throwable);
                        $this->_setChannel();
                        return false;
                    }
                );
            });
        }
    }

    /**
     * @return Channel|null
     */
    public function _getChannel() : ?Channel
    {
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