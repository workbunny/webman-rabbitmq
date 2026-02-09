<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connection\Traits;

use Psr\Log\LoggerInterface;

trait LoggerMethods
{
    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;

    /**
     * @param LoggerInterface|null $logger
     */
    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
