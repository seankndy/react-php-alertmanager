<?php
namespace SeanKndy\AlertManager\Routing;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
/**
 * Router is simply a collection of RoutableInterfaces that by default will
 * attempt to route to each one and upon successfully routing, stop any further
 * routing to routes added after it.
 *
 * This behavior can be changed with continue() and stop().  If addRoute() is
 * followed up with continue(), then the router will continue trying the next
 * route even after the alert was successfully routed.
 *
 * You can use stop() after a series of continue()s in order to stop the
 * continuable route-chain, but only if the Alert was indeed routed to at least
 * one of the continued routes.
 */
class Router implements RoutableInterface, \Countable
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
        foreach ($this->routes as $action => $route) {
            if ($promise = $route->route($alert)) {
                $promises[] = $promise;

                if ($action != self::CONTINUE) {
                    break;
                }
            } else if ($action == self::STOP && \count($promises) > 0) {
                break;
            }
        }
        if ($promises) {
            return \React\Promise\all($promises);
        }
        return null;
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
     * Does Router contain RoutableInterface $route?
     *
     * @param RoutableInterface $route
     *
     * @return bool
     */
    public function hasRoute(RoutableInterface $route)
    {
        return $this->routes->contains($route);
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
     * Set all routes
     *
     * @param \SplObjectStorage $routes
     *
     * @return self
     */
    public function setRoutes(\SplObjectStorage $routes)
    {
        $this->routes = $routes;

        return $this;
    }

    /**
     * Get all routes
     *
     * @return \SplObjectStorage|RoutableInterface[]
     */
    public function getRoutes($asArray = false)
    {
        return $asArray ? \iterator_to_array($this->routes) : $this->routes;
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

    /**
     * \Countable Implementation
     *
     * @return int
     */
    public function count()
    {
        return \count($this->routes);
    }

    public function __toString()
    {
        $i = 1;
        $sep = ' -- ';
        $str = '';
        foreach ($this->routes as $route) {
            $str .= "Routable #" . ($i++) . ": " . (string)$route . $sep;
        }
        return rtrim($str, $sep);
    }
}
