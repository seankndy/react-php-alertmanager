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
     * @var RouteCriteria[]
     */
    protected $criteria = [];

    public function __construct(string $key, $match)
    {
        $this->
    }

    /**
     * Set destination
     *
     * @return self
     */
    public function destination(RoutableInterface $destination = null)
    {
        $this->destination = $destination;

        return $this;
    }

    public function addCriteria(string $key, $match, string $logic = RouteCriteria::OR)
    {
        if (!$this->criteria) {
            $this->criteria[] = ($criteria = new RouteCriteria($logic));
        } else {
            $criteria = $this->criteria[count($this->criteria)-1];
            // if last criteria has new logic, make new criteria object
            // otherwise we'll reuse it
            if ($criteria->getLogic() != $logic) {
                $this->criteria[] = ($criteria = new RouteCriteria($logic));
            }
        }

        $criteria->add($key, $match);
        if (substr($key, 0, 6) == 'regex:') {
            $this->criteria[substr($key, 6)] = $match;
        } else {
            $this->criteria[$key] = $match;
        }
    }

    public function where($key, $match = null)
    {
        if (!$this->criteria) {
            $this->criteria[] = ($criteria = new RouteCriteria(RouteCriteria::AND));
        } else {
            $criteria = $this->criteria[count($this->criteria)-1];
        }

        if (\is_callable($key)) {
            $key($criteria); 
        }
        $criteria->add($key, $match);
        if (substr($key, 0, 6) == 'regex:') {
            $this->criteria[substr($key, 6)] = $match;
        } else {
            $this->criteria[$key] = $match;
        }


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
}
