<?php
namespace SeanKndy\AlertManager\Routing;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use Evenement\EventEmitter;
/**
 * Router is simply a collection of Routes that by default will
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
class Router extends EventEmitter implements RoutableInterface, \Countable
{
    /**
     * @var \SplObjectStorage<Route>
     */
    private \SplObjectStorage $routes;

    private ?Route $lastRoute = null;

    const END = 0;
    const CONTINUE = 1;
    const STOP = 2;

    public function __construct()
    {
        $this->routes = new \SplObjectStorage();
    }

    public function route(Alert $alert): ?PromiseInterface
    {
        $promises = [];
        $matched = false;
        foreach ($this->routes as $route) {
            $action = $this->routes[$route];
            if ($route->test($alert)) {
                $matched = true;
                if ($promise = $route->route($alert)) {
                    $promises[] = $promise;
                    $this->emit('routed', [$alert, $route]);

                    if ($action != self::CONTINUE) {
                        break;
                    }
                }
            }
            if ($action == self::STOP && $matched) {
                break;
            }
        }
        return \count($promises) > 0 ? \React\Promise\all($promises) : null;
    }

    public function addRoute(Route $route): self
    {
        $this->routes[$route] = self::END;
        $this->lastRoute = $route;

        return $this;
    }

    /**
     * @param Route[] $routes Array of Route objects to add
     */
    public function addRoutes(array $routes): self
    {
        foreach ($routes as $route) {
            $this->addRoute($route);
        }

        return $this;
    }

    public function hasRoute(Route $route): bool
    {
        return $this->routes->contains($route);
    }

    public function removeRoute(Route $route): self
    {
        $this->routes->detach($route);

        if ($route === $this->lastRoute) {
            foreach ($this->routes as $route) {
                $this->lastRoute = $route;
            }
        }

        return $this;
    }

    public function setRoutes(\SplObjectStorage $routes): self
    {
        $this->routes = $routes;

        return $this;
    }

    /**
     * Get all routes
     *
     * @return \SplObjectStorage|Route[]
     */
    public function getRoutes($asArray = false)
    {
        return $asArray ? \iterator_to_array($this->routes) : $this->routes;
    }

    /**
     * After the last-added Route routes, allow routing to continue to next
     * route in chain.
     */
    public function continue(): self
    {
        $this->routes[$this->lastRoute] = self::CONTINUE;

        return $this;
    }

    /**
     * After the last-added Route routes, end route chain (this is the default behavior)
     */
    public function end(): self
    {
        $this->routes[$this->lastRoute] = self::END;

        return $this;
    }

    /**
     * After the last-added Route evaluates, stop regardless if it routes or
     * not but only if the Alert has been routed to something else prior.
     */
    public function stop(): self
    {
        $this->routes[$this->lastRoute] = self::STOP;

        return $this;
    }

    public function count(): int
    {
        return \count($this->routes);
    }

    public function __toString()
    {
        $i = 1;
        $sep = ' -- ';
        $str = '';
        foreach ($this->routes as $route) {
            $str .= "(Routable #" . ($i++) . ": [" . (string)$route . "]" . $sep;
        }
        return rtrim($str, $sep);
    }
}
