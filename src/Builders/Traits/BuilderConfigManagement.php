<?php

declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Builders\Traits;

use Workbunny\WebmanRabbitMQ\BuilderConfig;

trait BuilderConfigManagement
{
    /**
     * @var BuilderConfig
     */
    private BuilderConfig $_builderConfig;

    /**
     * @return BuilderConfig
     */
    public function getBuilderConfig(): BuilderConfig
    {
        return $this->_builderConfig;
    }

    /**
     * @param BuilderConfig $builderConfig
     */
    public function setBuilderConfig(BuilderConfig $builderConfig): void
    {
        $this->_builderConfig = $builderConfig;
    }
}
