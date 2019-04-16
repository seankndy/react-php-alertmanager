<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;
use React\Promise\PromiseInterface;

interface RoutableInterface
{
    /**
     * Handle routing of Alert $alert
     *
     * @return PromiseInterface\null Should return a Promise or NULL if $alert
     *     is not consumable/routable by the RoutableInterface.
     */
    public function route(Alert $alert) : ?PromiseInterface;
}
