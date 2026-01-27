<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Exceptions;

use RuntimeException;

class WebmanRabbitMQException extends RuntimeException
{
    protected mixed $extra;

    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null, mixed $extra = null)
    {
        $this->extra = $extra;
        parent::__construct($message, $code, $previous);
    }
}