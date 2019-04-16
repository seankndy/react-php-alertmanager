<?php
namespace SeanKndy\AlertManager\Routing;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;

class Router implements RoutableInterface
{
    /**
     * @var RouteInterface[]
     */
    private $routes = [];

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        foreach ($this->routes as $route) {
            if ($promise = $route->route($alert)) {
                return $promise;
            }
        }
        return \React\Promise\resolve([]);
    }

    /**
     * Add Route
     *
     * @return self
     */
    public function addRoute(RoutableInterface $route)
    {
        $this->routes[] = $route;

        return $this;
    }
}
