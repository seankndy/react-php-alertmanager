<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;
use React\Promise\PromiseInterface;
/**
 * A ReceivableInterface is a type of RoutableInterface that should 'handle'
 * the Alert rather than deferring/routing to another routable.
 *
 */
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
     * Determine if this Receiver is ready/capable of receiving for Alert $alert
     *
     * @param Alert $alert
     *
     * @return bool
     */
    public function isReceivable(Alert $alert) : bool;

    /**
     * Get unique identifier for this ReceivableInterface
     *
     * @return mixed
     */
    public function receiverId();
}
