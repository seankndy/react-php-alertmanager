<?php
namespace SeanKndy\AlertManager\Routing;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;

class Router implements RoutableInterface
{
    /**
     * @var \SplObjectStorage
     */
    private $routes;
    /**
     * @var RoutableInterface
     */
    private $lastRoute = null;

    public function __construct()
    {
        $this->routes = new \SplObjectStorage();
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        $promises = [];
        foreach ($this->routes as $route => $continue) {
            if ($promise = $route->route($alert)) {
                $promises[] = $promise;

                if (!$continue) {
                    break;
                }
            }
        }
        return \React\Promise\all($promises);
    }

    /**
     * Add Route
     *
     * @return self
     */
    public function addRoute(RoutableInterface $route)
    {
        $this->routes[$route] = false;
        $this->lastRoute = $route;

        return $this;
    }

    /**
     * Remove Route
     *
     * @return self
     */
    public function removeRoute(RoutableInterface $route)
    {
        $this->routes->detach($route);

        if ($route === $this->lastRoute) {
            foreach ($this->routes as $route => $continue) {
                $this->lastRoute = $route;
            }
        }

        return $this;
    }

    /**
     * After the last-added Route routes, allow routing to continue to next
     * route.
     *
     * @return self
     */
    public function continue()
    {
        $this->routes[$this->lastRoute] = true;

        return $this;
    }
}
