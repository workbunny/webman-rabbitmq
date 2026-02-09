<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Exceptions;

use RuntimeException;
use Workerman\Coroutine;

class WebmanRabbitMQException extends RuntimeException
{
    protected mixed $extra;

    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null, mixed $extra = null)
    {
        $this->extra = $extra;
        $coroutine = Coroutine::getCurrent()->id();
        parent::__construct("[Co: $coroutine] $message", $code, $previous);
    }
}
