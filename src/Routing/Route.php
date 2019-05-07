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
     * @var Criteria
     */
    protected $criteria = null;

    public function __construct(RoutableInterface $destination = null)
    {
        $this->destination = $destination;
    }

    /**
     * Create Route with destination $destination
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
            $this->criteria = new Criteria();
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
     * Test route on Alert
     *
     * @return bool
     */
    public function test(Alert $alert)
    {
        return ($this->criteria && $this->criteria->matches($alert));
    }

    /**
     * {@inheritDoc} Route Alert to destination
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        if (!$this->destination) {
            return \React\Promise\resolve([]);
        }
        return $this->destination->route($alert);
    }

    /**
     * Get the destination
     *
     * @return RoutableInterface|null
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * Set the value of Destination
     *
     * @param RoutableInterface $destination
     *
     * @return self
     */
    public function setDestination(RoutableInterface $destination = null)
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * Get value of criteria
     *
     * @return Criteria|null
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * Set the value of criteria
     *
     * @param Criteria $criteria
     *
     * @return self
     */
    public function setCriteria(Criteria $criteria)
    {
        $this->criteria = $criteria;

        return $this;
    }

    public function __toString()
    {
        return 'criteria=' . (string)$this->criteria . '; ' .
            'destination=('.(\is_null($this->destination) ? 'NULL' : (string)$this->destination).')';
    }
}
