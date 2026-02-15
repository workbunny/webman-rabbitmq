<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Connection\Traits;

trait MechanismMethods
{
    /**
     * @var array<string, callable>
     */
    protected array $mechanismHandlers = [];

    /**
     * @param string $mechanism
     * @param callable $handler
     */
    public function registerMechanismHandler(string $mechanism, callable $handler): void
    {
        $this->mechanismHandlers[$mechanism] = $handler;
    }

    /**
     * @return array
     */
    public function getMechanismHandlers(): array
    {
        return $this->mechanismHandlers;
    }

    /**
     * @param string $mechanism
     * @return callable|null
     */
    public function getMechanismHandler(string $mechanism): callable|null
    {
        return $this->mechanismHandlers[$mechanism] ?? null;
    }
}
