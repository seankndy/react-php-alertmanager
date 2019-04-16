<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;
use React\Promise\PromiseInterface;

class RoutableInterface
{
    /**
     * Handle routing of Alert $alert
     *
     * @return PromiseInterface
     */
    public function route(Alert $alert) : PromiseInterface;
}
