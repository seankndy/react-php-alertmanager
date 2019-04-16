<?php
namespace SeanKndy\AlertManager\Routing;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;

class Router implements RoutableInterface
{
    /**
     * @var LoopInterface
     */
    private $loop;
    /**
     * @var RouteInterface[]
     */
    private $routes = [];

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert) : PromiseInterface
    {
        foreach ($this->routes as $route) {
            if (($promise = $route->route($alert)) !== null) {
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
    public function addRoute(AbstractRoute $route)
    {
        $this->routes[] = $route;

        return $this;
    }
}
