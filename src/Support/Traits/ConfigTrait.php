<?php
namespace SeanKndy\AlertManager\Support\Traits;

trait ConfigTrait
{
    protected $config;

    /**
     * Set a single configuration item
     *
     * @param string $key Key of config item
     * @Param mixed $val Value of config item
     *
     * @return self
     */
    public function setConfigItem($key, $val)
    {
        $this->config[$key] = $val;

        return $this;
    }

    /**
     * Get config array
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set config array
     *
     * @param array $config
     *
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }
}
