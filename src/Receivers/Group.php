<?php
namespace SeanKndy\AlertManager\Receivers;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;

class Group implements RoutableInterface
{
    /**
     * @var AbstractReceiver[]
     */
    private $receivers = [];

    public function __construct(array $receivers = [])
    {
        $this->receivers = $receivers;
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        $promises = [];
        foreach ($this->receivers as $receiver) {
            $promises[] = $receiver->route($alert);
        }
        return \React\Promise\all($promises);
    }

    /**
     * Get the value of Receivers
     *
     * @return AbstractReceiver[]
     */
    public function getReceivers()
    {
        return $this->receivers;
    }

    /**
     * Set the value of Receivers
     *
     * @param AbstractReceiver[] receivers
     *
     * @return self
     */
    public function setReceivers(array $receivers)
    {
        $this->receivers = $receivers;

        return $this;
    }

    /**
     * Add a Receiver to group
     *
     * @param AbstractReceiver[] receivers
     *
     * @return self
     */
    public function addReceiver(AbstractReceiver $receiver)
    {
        $this->receivers[] = $receivers;

        return $this;
    }
}
