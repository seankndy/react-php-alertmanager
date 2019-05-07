<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;
use React\Promise\PromiseInterface;

interface ReceivableInterface extends RoutableInterface
{
    /**
     * Receive an Alert to act on it.
     *
     * @param Alert $alert Alert to act on
     *
     * @return PromiseInterface
     */
    public function receive(Alert $alert) : PromiseInterface;

    /**
     * Return a unique identifier for the receiver
     *
     * @return mixed
     */
    public function getId();
}
