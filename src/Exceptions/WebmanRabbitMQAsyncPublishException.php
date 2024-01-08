<?php
declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Exceptions;

use RuntimeException;
use Throwable;
use Workbunny\WebmanRabbitMQ\BuilderConfig;

class WebmanRabbitMQAsyncPublishException extends WebmanRabbitMQException
{
    protected BuilderConfig $data;

    public function __construct(string $message = "", int $code = 0, ?BuilderConfig $data = null, ?Throwable $previous = null)
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }
}