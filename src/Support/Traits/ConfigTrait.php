<?php

namespace SeanKndy\AlertManager\Support\Traits;

trait ConfigTrait
{
    protected array $config = [];

    /**
     * Set a single configuration item
     *
     * @param string $key Key of config item
     * @param mixed $val Value of config item
     */
    public function setConfigItem(string $key, $val): self
    {
        $this->config[$key] = $val;

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }
}
