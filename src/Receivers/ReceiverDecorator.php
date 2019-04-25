<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;
use React\Promise\PromiseInterface;

abstract class ReceiverDecorator implements ReceivableInterface
{
    /**
     * @var AbstractReceiver
     */
    protected $receiver;

    public function __construct(AbstractReceiver $receiver)
    {
        $this->receiver = $receiver;
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        if (!$this->receiver->isReceivable($alert)) {
            return null;
        }
        return $this->receive($alert);
    }

    /**
     * Get the value of Receiver
     *
     * @return AbstractReceiver
     */
    public function getReceiver()
    {
        return $this->receiver;
    }

    /**
     * Set the value of Receiver
     *
     * @param AbstractReceiver receiver
     *
     * @return self
     */
    public function setReceiver(AbstractReceiver $receiver)
    {
        $this->receiver = $receiver;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return (string)$this->receiver;
    }
}
