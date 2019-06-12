<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use React\Promise\PromiseInterface;

abstract class ReceiverDecorator implements ReceivableInterface
{
    /**
     * Receiver we are decorating.
     * @var ReceivableInterface
     */
    protected $receiver;

    public function __construct(ReceivableInterface $receiver)
    {
        $this->receiver = $receiver;
    }

    /**
     * {@inheritDoc}
     */
    public function isReceivable(Alert $alert) : bool
    {
        return $this->receiver->isReceivable($alert);
    }

    /**
     * {@inheritDoc}
     */
    public function receiverId()
    {
        return $this->receiver->receiverId();
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        if ($this->isReceivable($alert)) {
            return $this->receive($alert);
        }
        return null;
    }

    /**
     * Get the value of Receiver
     *
     * @return ReceivableInterface
     */
    public function getReceiver()
    {
        return $this->receiver;
    }

    /**
     * Find the 'real' receiver behind any layers of decorators
     *
     * @return ReceivableInterface
     */
    public function resolveReceiver($that = null)
    {
        if (!$that) {
            $that = $this;
        }
        return ($that->receiver instanceof self)
            ? $this->resolveReceiver($this->receiver)
            : $this->receiver;
    }

    /**
     * Set the value of Receiver
     *
     * @param ReceivableInterface $receiver
     *
     * @return self
     */
    public function setReceiver(ReceivableInterface $receiver)
    {
        $this->receiver = $receiver;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return \get_class($this) . '(' . (string)$this->receiver . ')';
    }
}
