<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ;

class Constants
{
    public const DIRECT = 'direct';
    public const FANOUT = 'fanout';
    public const TOPIC = 'topic';
    public const HEADER = 'header';
    public const DELAYED = 'x-delayed-message';

    public const ACK = 'ack';
    public const NACK = 'nack';
    public const REQUEUE = 'requeue';

    public const REJECT = 'reject';

    public const DELIVERY_MODE_NON_PERSISTENT = 1;
    public const DELIVERY_MODE_PERSISTENT = 2;
}
