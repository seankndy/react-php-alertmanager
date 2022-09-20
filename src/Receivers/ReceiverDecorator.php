<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use React\Promise\PromiseInterface;

abstract class ReceiverDecorator implements ReceivableInterface
{
    /**
     * Receiver we are decorating.
     */
    protected ReceivableInterface $receiver;

    public function __construct(ReceivableInterface $receiver)
    {
        $this->receiver = $receiver;
    }

    public function isReceivable(Alert $alert) : bool
    {
        return $this->receiver->isReceivable($alert);
    }

    public function receiverId()
    {
        return $this->receiver->receiverId();
    }

    public function route(Alert $alert) : ?PromiseInterface
    {
        if ($this->isReceivable($alert)) {
            return $this->receive($alert);
        }
        return null;
    }

    public function getReceiver(): ReceivableInterface
    {
        return $this->receiver;
    }

    /**
     * Find the 'real' receiver behind any layers of decorators
     */
    public function resolveReceiver($that = null): ReceivableInterface
    {
        if (!$that) {
            $that = $this;
        }
        return ($that->receiver instanceof self)
            ? $this->resolveReceiver($this->receiver)
            : $this->receiver;
    }


    public function setReceiver(ReceivableInterface $receiver): self
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function __toString(): string
    {
        return \get_class($this) . '(' . (string)$this->receiver . ')';
    }
}
