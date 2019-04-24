<?php
namespace SeanKndy\AlertManager\Routing;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;
/**
 * Implements a group of routables.  This is simply a Router which when routes
 * are added to it, it sets them to CONTINUE instead of END.
 *
 * This is similar to a Router which also groups Routables (in fact, we inherit
 * from it), but the difference is that a Group will not stop routing to the
 * routables after the first hit but instead loop all the way through delivering
 * to every one.
 */
class Group extends Router
{
    public function __construct(array $routables = [])
    {
        foreach ($routables as $routable) {
            $this->addRoute($routable);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addRoute(RoutableInterface $route)
    {
        return parent::addRoute($route)->continue();
    }

    /**
     * {@inheritDoc}
     */
    public function setRoutes(\SplObjectStorage $routes)
    {
        $this->routes = new \SplObjectStorage();
        foreach ($routes as $action => $route) {
            $this->routes[$route] = Router::CONTINUE;
        }

        return $this;
    }
}
