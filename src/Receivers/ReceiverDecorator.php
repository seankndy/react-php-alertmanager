<?php
namespace SeanKndy\AlertManager\Receivers;

use React\Promise\PromiseInterface;

class ReceiverDecorator implements ReceivableInterface
{
    /**
     * @var AbstractReceiver
     */
    protected $receiver;

    public function __construct(AbstractReceiver $receiver)
    {
        $this->receiver = $receiver;
    }
}
