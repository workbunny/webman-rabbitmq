<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

class BuilderConfig
{
    protected string $_queue = '';
    protected bool $_passive = false;
    protected bool $_durable = true;
    protected bool $_exclusive = false;
    protected bool $_autoDelete = false;
    protected bool $_nowait = false;
    protected array $_arguments = [];

    protected int $_prefetchSize = 0;
    protected int $_prefetchCount = 0;
    protected bool $_global = false;

    protected string $_body = '';
    protected array $_headers = [
        'content-type'  => 'text/plain',
        'delivery-mode' => Constants::DELIVERY_MODE_PERSISTENT
    ];
    protected string $_exchange = '';
    protected string $_exchangeType = Constants::DIRECT;
    protected string $_routingKey = '';
    protected bool $_internal = false;
    protected bool $_mandatory = false;
    protected bool $_immediate = false;

    protected string $_consumerTag = '';
    protected bool $_noLocal = false;
    protected bool $_noAck = false;

    protected $_callback;

    /**
     * 转数组
     * @return array
     */
    public function __invoke(): array
    {
        $result = [];
        $ref = new \ReflectionObject($this);
        // 获取所有受保护的属性
        $properties = $ref->getProperties(\ReflectionProperty::IS_PROTECTED);

        foreach ($properties as $property) {
            // 设置属性为可访问，否则无法读取受保护属性的值
            $property->setAccessible(true);
            $result[$property->getName()] = $property->getValue($this);
        }
        return $result;
    }

    public function getExchangeType(): string
    {
        return $this->_exchangeType;
    }

    public function setExchangeType(string $exchangeType): void
    {
        $this->_exchangeType = $exchangeType;
    }

    public function isInternal(): bool
    {
        return $this->_internal;
    }

    public function setInternal(bool $internal): void
    {
        $this->_internal = $internal;
    }

    public function getQueue(): string
    {
        return $this->_queue;
    }

    public function setQueue(string $queue): void
    {
        $this->_queue = $queue;
    }

    public function isPassive(): bool
    {
        return $this->_passive;
    }

    public function setPassive(bool $passive): void
    {
        $this->_passive = $passive;
    }

    public function isDurable(): bool
    {
        return $this->_durable;
    }

    public function setDurable(bool $durable): void
    {
        $this->_durable = $durable;
    }

    public function isExclusive(): bool
    {
        return $this->_exclusive;
    }

    public function setExclusive(bool $exclusive): void
    {
        $this->_exclusive = $exclusive;
    }

    public function isAutoDelete(): bool
    {
        return $this->_autoDelete;
    }

    public function setAutoDelete(bool $autoDelete): void
    {
        $this->_autoDelete = $autoDelete;
    }

    public function isNowait(): bool
    {
        return $this->_nowait;
    }

    public function setNowait(bool $nowait): void
    {
        $this->_nowait = $nowait;
    }

    public function getArguments(): array
    {
        return $this->_arguments;
    }

    public function setArguments(array $arguments): void
    {
        $this->_arguments = $arguments;
    }

    public function getPrefetchSize(): int
    {
        return $this->_prefetchSize;
    }

    public function setPrefetchSize(int $prefetchSize): void
    {
        $this->_prefetchSize = $prefetchSize;
    }

    public function getPrefetchCount(): int
    {
        return $this->_prefetchCount;
    }

    public function setPrefetchCount(int $prefetchCount): void
    {
        $this->_prefetchCount = $prefetchCount;
    }

    public function isGlobal(): bool
    {
        return $this->_global;
    }

    public function setGlobal(bool $global): void
    {
        $this->_global = $global;
    }

    public function getBody(): string
    {
        return $this->_body;
    }

    public function setBody(string $body): void
    {
        $this->_body = $body;
    }

    public function getHeaders(): array
    {
        return $this->_headers;
    }

    public function setHeaders(array $headers): void
    {
        $this->_headers = $headers;
    }

    public function getExchange(): string
    {
        return $this->_exchange;
    }

    public function setExchange(string $exchange): void
    {
        $this->_exchange = $exchange;
    }

    public function getRoutingKey(): string
    {
        return $this->_routingKey;
    }

    public function setRoutingKey(string $routingKey): void
    {
        $this->_routingKey = $routingKey;
    }

    public function isMandatory(): bool
    {
        return $this->_mandatory;
    }

    public function setMandatory(bool $mandatory): void
    {
        $this->_mandatory = $mandatory;
    }

    public function isImmediate(): bool
    {
        return $this->_immediate;
    }

    public function setImmediate(bool $immediate): void
    {
        $this->_immediate = $immediate;
    }

    public function getConsumerTag(): string
    {
        return $this->_consumerTag;
    }

    public function setConsumerTag(string $consumerTag): void
    {
        $this->_consumerTag = $consumerTag;
    }

    public function isNoLocal(): bool
    {
        return $this->_noLocal;
    }

    public function setNoLocal(bool $noLocal): void
    {
        $this->_noLocal = $noLocal;
    }

    public function isNoAck(): bool
    {
        return $this->_noAck;
    }

    public function setNoAck(bool $noAck): void
    {
        $this->_noAck = $noAck;
    }

    public function setCallback(callable $callback) : void
    {
        $this->_callback = $callback;
    }

    public function getCallback() : callable
    {
        return $this->_callback;
    }
}
