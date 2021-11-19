<?php
namespace SeanKndy\AlertManager\Receivers;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;

class Group implements RoutableInterface, \Countable
{
    private \SplObjectStorage $receivers;

    public function __construct(array $receivers = [])
    {
        $this->setReceivers($receivers);
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert): ?PromiseInterface
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
    public function getReceivers(): array
    {
        return \iterator_to_array($this->receivers);
    }

    /**
     * Set the value of $receivers
     */
    public function setReceivers(array $receivers): self
    {
        $this->receivers = new \SplObjectStorage();
        foreach ($receivers as $r) {
            $this->receivers->attach($r);
        }

        return $this;
    }

    /**
     * Add a Receiver to group
     */
    public function addReceiver(ReceivableInterface $receiver): self
    {
        $this->receivers->attach($receiver);

        return $this;
    }

    /**
     * Check if Receiver in group
     */
    public function hasReceiver(ReceivableInterface $receiver): bool
    {
        return $this->receivers->contains($receiver);
    }

    /**
     * Remove Receiver from group
     */
    public function removeReceiver(ReceivableInterface $receiver): self
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
