<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Exceptions;

use Throwable;
use Workbunny\WebmanRabbitMQ\BuilderConfig;

class WebmanRabbitMQChannelFulledException extends WebmanRabbitMQException
{
    protected ?BuilderConfig $builderConfig;

    public function __construct(string $message = '', int $code = 0, ?BuilderConfig $builderConfig = null, ?Throwable $previous = null)
    {
        $this->builderConfig = $builderConfig;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return BuilderConfig|null
     */
    public function getBuilderConfig(): ?BuilderConfig
    {
        return $this->builderConfig;
    }
}
