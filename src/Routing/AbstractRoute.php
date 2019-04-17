<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;
use React\Promise\PromiseInterface;

abstract class AbstractRoute implements RoutableInterface
{
    /**
     * @var RoutableInterface
     */
    protected $destination = null;

    public function __construct(RoutableInterface $destination = null)
    {
        $this->destination = $destination;
    }

    /**
     * Route Alert to destination (if matching)
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        if (!$this->matches($alert)) {
            return null; // not routable by this route, return NULL
        }
        if (!$this->destination) {
            return \React\Promise\resolve([]);
        }
        return $this->destination->route($alert);
    }

    /**
     * Determine if $alert matches this route
     *
     * @return bool
     */
    abstract protected function matches(Alert $alert) : bool;

    /**
     * Create new AbstractRoute and return it
     *
     * @return AbstractRoute
     */
    abstract public static function define($criteria,
        ?RoutableInterface $destination = null) : AbstractRoute;

    /**
     * Set destination
     *
     * @return self
     */
    public function destination($destination)
    {
        $this->destination = $destination;

        return $this;
    }
}
