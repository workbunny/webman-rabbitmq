<?php declare(strict_types=1);

namespace Workbunny\WebmanRabbitMQ\Traits;

trait ConfigMethods
{
    /**
     * @var array
     */
    protected array $config = [];

    /**
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, mixed $default): mixed
    {
        $config = $this->getConfigs();
        $keyArray = explode('.', $key);
        $found = true;
        foreach ($keyArray as $index) {
            if (!isset($config[$index])) {
                $found = false;
                break;
            }
            $config = $config[$index];
        }

        return $found ? $config : $default;
    }

    /**
     * @return array
     */
    public function getConfigs(): array
    {
        return $this->config;
    }
}