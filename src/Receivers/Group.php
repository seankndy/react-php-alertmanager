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
        return \count($promises) > 0 ? \React\Promise\all($promises) : null;
    }

    /**
     * Get the value of Receivers
     *
     * @return ReceivableInterface[]
     */
    public function getReceivers()
    {
        return \iterator_to_array($this->receivers);
    }

    /**
     * Set the value of $receivers
     *
     * @param ReceivableInterface[] $receivers
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
     * @param ReceivableInterface receiver
     *
     * @return self
     */
    public function addReceiver(ReceivableInterface $receiver)
    {
        $this->receivers->attach($receiver);

        return $this;
    }

    /**
     * Check if Receiver in group
     *
     * @param ReceivableInterface $receiver
     *
     * @return bool
     */
    public function hasReceiver(ReceivableInterface $receiver)
    {
        return $this->receivers->contains($receiver);
    }

    /**
     * Remove Receiver from group
     *
     * @param ReceivableInterface $receiver
     *
     * @return self
     */
    public function removeReceiver(ReceivableInterface $receiver)
    {
        $this->receivers->detach($receiver);

        return $this;
    }

    /**
     * Countable implementation
     *
     * @return int
     */
    public function count()
    {
        return \count($this->receivers);
    }

    public function __toString()
    {
        $i = 1;
        $sep = ' -- ';
        $str = '';
        foreach ($this->receivers as $receiver) {
            $str .= "Receiver #" . ($i++) . ": [" . (string)$receiver . "]" . $sep;
        }
        return rtrim($str, $sep);
    }
}
