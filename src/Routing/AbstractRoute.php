<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;

abstract class AbstractRoute
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
        if (!$destination || !$this->matches($alert)) {
            return null;
        }
        return $destination->route($alert);
    }

    /**
     * Determine if $alert matches this route
     *
     * @return bool
     */
    abstract protected function matches(Alert $alert) : bool

    /**
     * Create new AbstractRoute and return it
     *
     * @return AbstractRoute
     */
    abstract public static function define($criteria,
        RoutableInterface $destination) : AbstractRoute;
}
