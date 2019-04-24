<?php
namespace SeanKndy\AlertManager\Receivers;

use React\Promise\PromiseInterface;

interface ReceivableInterface
{
    /**
     * Receive an Alert to act on it.
     *
     * @param Alert $alert Alert to act on
     *
     * @return PromiseInterface
     */
    public function receive(Alert $alert) : PromiseInterface;
}
