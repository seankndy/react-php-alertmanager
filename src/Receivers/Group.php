<?php
namespace SeanKndy\AlertManager\Receivers;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;

class Group implements RoutableInterface, \Countable
{
    private \SplObjectStorage $receivers;

    /**
     * @param ReceivableInterface[] $receivers
     */
    public function __construct(array $receivers = [])
    {
        $this->setReceivers($receivers);
    }

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
     * Get all receivers in group as an array.
     *
     * @return ReceivableInterface[]
     */
    public function getReceivers(): array
    {
        return \iterator_to_array($this->receivers);
    }

    /**
     * @param ReceivableInterface[] $receivers
     */
    public function setReceivers(array $receivers): self
    {
        $this->receivers = new \SplObjectStorage();
        foreach ($receivers as $r) {
            $this->receivers->attach($r);
        }

        return $this;
    }

    public function addReceiver(ReceivableInterface $receiver): self
    {
        $this->receivers->attach($receiver);

        return $this;
    }

    public function hasReceiver(ReceivableInterface $receiver): bool
    {
        return $this->receivers->contains($receiver);
    }

    public function removeReceiver(ReceivableInterface $receiver): self
    {
        $this->receivers->detach($receiver);

        return $this;
    }

    public function count(): int
    {
        return \count($this->receivers);
    }

    public function __toString(): string
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
