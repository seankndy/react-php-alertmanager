<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;
use React\Promise\PromiseInterface;

class Route implements RoutableInterface
{
    /**
     * @var RoutableInterface
     */
    protected $destination = null;
    /**
     * @var RouteCriteria
     */
    protected $criteria = null;

    public function __construct(RoutableInterface $destination = null)
    {
        $this->destination = $destination;
    }

    /**
     * Create Route for destination $receiver
     *
     * @return Route
     */
    public static function toDestination(RoutableInterface $destination = null)
    {
        return new self($destination);
    }

    /**
     * Define AND criteria
     *
     * @return self
     */
    public function where($key, $match = null)
    {
        if (!$this->criteria) {
            $this->criteria = new RouteCriteria();
        }
        $this->criteria = $this->criteria->where($key, $match);
        return $this;
    }

    /**
     * Define OR criteria
     *
     * @return self
     */
    public function orWhere($key, $match = null)
    {
        if (!$this->criteria) {
            throw new \RuntimeException("Cannot call orWhere() before where()");
        }
        $this->criteria = $this->criteria->orWhere($key, $match);
        return $this;
    }

    /**
     * Route Alert to destination (if matching)
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        if (!$this->criteria || !$this->criteria->matches($alert)) {
            return null; // not routable by this route, return NULL
        }
        if (!$this->destination) {
            return \React\Promise\resolve([]);
        }
        return $this->destination->route($alert);
    }
}
