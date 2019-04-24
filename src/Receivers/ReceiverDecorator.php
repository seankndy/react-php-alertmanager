<?php
namespace SeanKndy\AlertManager\Receivers;

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

}
