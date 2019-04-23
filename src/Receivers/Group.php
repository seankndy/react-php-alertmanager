<?php
namespace SeanKndy\AlertManager\Receivers;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;

class Group implements RoutableInterface, \Countable
{
    /**
     * @var \SplObjectStorage
     */
    private $receivers;

    public function __construct(array $receivers = [])
    {
        $this->setReceivers($receivers);
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        $promises = [];
        foreach ($this->receivers as $receiver) {
            if ($promise = $receiver->route($alert)) {
                $promises[] = $promise;
            }
        }
        return $promises ? \React\Promise\all($promises) : null;
    }

    /**
     * Get the value of Receivers
     *
     * @return AbstractReceiver[]
     */
    public function getReceivers()
    {
        return \iterator_to_array($this->receivers);
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
        $this->receivers = new \SplObjectStorage();
        foreach ($receivers as $r) {
            $this->receivers->attach($r);
        }

        return $this;
    }

    /**
     * Add a Receiver to group
     *
     * @param AbstractReceiver receiver
     *
     * @return self
     */
    public function addReceiver(AbstractReceiver $receiver)
    {
        $this->receivers->attach($receiver);

        return $this;
    }

    /**
     * Check if Receiver in group
     *
     * @param AbstractReceiver $receiver
     *
     * @return bool
     */
    public function containsReceiver(AbstractReceiver $receiver)
    {
        return $this->receivers->contains($receiver);
    }

    /**
     * Remove Receiver from group
     *
     * @param AbstractReceiver $receiver
     *
     * @return self
     */
    public function removeReceiver(AbstractReceiver $receiver)
    {
        $this->receivers->detach($receiver);

        return $this;
    }

    public function count()
    {
        return \count($this->receivers);
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        $str = \implode(PHP_EOL, \iterator_to_array($this->receivers));
        
        return $str;
    }
}
