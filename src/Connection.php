<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

use Bunny\Channel;
use Bunny\Message;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Closure;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use support\Log;
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

    /**
     * @var Closure|mixed|null
     */
    protected ?Closure $_errorCallback = null;


    public function __construct(array $config = [])
    {
        $this->_config = $config ?? config('plugin.workbunny.webman-rabbitmq.app');
        $this->_errorCallback = $this->_config['error_callback'] ?? null;
        $this->_client = new AsyncClient($this->_config);
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if(
            $this->_client instanceof AsyncClient and
            $this->_client->isConnected()
        ){
            $this->_client->disconnect();
        }
        $this->_client = null;
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
        $this->client()->connect()->then(
            function (AsyncClient $client){
                return $client->channel()->then(function (Channel $channel){
                    return $channel;
                },function (\Throwable $throwable){
                    $this->close();
                    if($this->_errorCallback){
                        ($this->_errorCallback)($throwable);
                    }
                });
            },
            function (\Throwable $throwable){
                $this->close();
                if($this->_errorCallback){
                    ($this->_errorCallback)($throwable);
                }
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
                function (\Throwable $throwable){
                    if($this->_errorCallback){
                        ($this->_errorCallback)($throwable);
                    }
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
                function (\Throwable $throwable){
                    if($this->_errorCallback){
                        ($this->_errorCallback)($throwable);
                    }
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
                function (\Throwable $throwable){
                    if($this->_errorCallback){
                        ($this->_errorCallback)($throwable);
                    }
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
                function (\Throwable $throwable){
                    if($this->_errorCallback){
                        ($this->_errorCallback)($throwable);
                    }
                }
            );
        })->then(function (Channel $channel) use ($abstractMessage) {
            //Waiting for messages
            $channel->consume(
                function (Message $message, Channel $channel, AsyncClient $client) use ($abstractMessage) {
                    $tag = ($abstractMessage->getCallback())($message, $channel, $client);
                    switch ($tag) {
                        case Constants::ACK:
                            $res = $channel->ack($message);
                            break;
                        case Constants::NACK:
                            $res = $channel->nack($message);
                            break;
                        case Constants::REQUEUE:
                        default:
                            $res = $channel->reject($message);
                            break;
                    }
                    $res->then(
                        function (){

                        },
                        function (\Throwable $throwable){
                            if($this->_errorCallback){
                                ($this->_errorCallback)($throwable);
                            }
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
                function (\Throwable $throwable){
                    if($this->_errorCallback){
                        ($this->_errorCallback)($throwable);
                    }
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
        if($this->client()->isConnected()){
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
                        $this->_setChannel();
                        $this->close();
                    }
                    return true;
                },
                function (\Throwable $throwable){
                    $this->_setChannel();
                    $this->close();
                    if($this->_errorCallback){
                        ($this->_errorCallback)($throwable);
                    }
                    return false;
                }
            );
        } else{
            return $this->client()->connect()->then(function (AsyncClient $client){
                return $client->channel()->then(function (Channel $channel){
                    $this->_setChannel($channel);
                    return $channel;
                },function (\Throwable $throwable){
                    $this->_setChannel();
                    $this->close();
                    if($this->_errorCallback){
                        ($this->_errorCallback)($throwable);
                    }
                    return false;
                });
            },function (\Throwable $throwable){
                $this->_setChannel();
                $this->close();
                if($this->_errorCallback){
                    ($this->_errorCallback)($throwable);
                }
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
                    function (\Throwable $throwable){
                        $this->_setChannel();
                        $this->close();
                        if($this->_errorCallback){
                            ($this->_errorCallback)($throwable);
                        }
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
                    function (\Throwable $throwable){
                        $this->_setChannel();
                        $this->close();
                        if($this->_errorCallback){
                            ($this->_errorCallback)($throwable);
                        }
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
                    function (\Throwable $throwable){
                        $this->_setChannel();
                        $this->close();
                        if($this->_errorCallback){
                            ($this->_errorCallback)($throwable);
                        }
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
                            $this->_setChannel();
                            $this->close();
                        }
                        return true;
                    },
                    function (\Throwable $throwable){
                        $this->_setChannel();
                        $this->close();
                        if($this->_errorCallback){
                            ($this->_errorCallback)($throwable);
                        }
                        return false;
                    }
                );
            });
        }
    }

    /**
     * @return Channel|null
     */
    protected function _getChannel() : ?Channel
    {
        return $this->_channel;
    }

    /**
     * @param Channel|null $channel
     * @return void
     */
    protected function _setChannel(?Channel $channel = null){
        $this->_channel = $channel;
    }


}