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

    const END = 0;
    const CONTINUE = 1;
    const STOP = 2;

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
        foreach ($this->routes as $route => $action) {
            if ($promise = $route->route($alert)) {
                $promises[] = $promise;

                if ($action != self::CONTINUE) {
                    break;
                }
            } else if ($action == self::STOP && \count($promises) > 0) {
                break;
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
        $this->routes[$route] = self::END;
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
     * route in chain.
     *
     * @return self
     */
    public function continue()
    {
        $this->routes[$this->lastRoute] = self::CONTINUE;

        return $this;
    }

    /**
     * After the last-added Route routes, end route chain (this is the default behavior)
     *
     * @return self
     */
    public function end()
    {
        $this->routes[$this->lastRoute] = self::END;

        return $this;
    }

    /**
     * After the last-added Route evaluates, stop regardless if it routes or
     * not but only if the Alert has been routed to something else prior.
     *
     * @return self
     */
    public function stop()
    {
        $this->routes[$this->lastRoute] = self::STOP;

        return $this;
    }
}
