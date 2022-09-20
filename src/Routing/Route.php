<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;
use React\Promise\PromiseInterface;

class Route implements RoutableInterface
{
    protected ?RoutableInterface $destination = null;

    protected ?Criteria $criteria = null;

    public function __construct(RoutableInterface $destination = null)
    {
        $this->destination = $destination;
    }

    public static function toDestination(RoutableInterface $destination = null): self
    {
        return new self($destination);
    }

    /**
     * Define AND criteria
     */
    public function where($key, $match = null): self
    {
        if (!$this->criteria) {
            $this->criteria = new Criteria();
        }
        $this->criteria = $this->criteria->where($key, $match);

        return $this;
    }

    /**
     * Define OR criteria
     */
    public function orWhere($key, $match = null): self
    {
        if (!$this->criteria) {
            throw new \RuntimeException("Cannot call orWhere() before where()");
        }
        $this->criteria = $this->criteria->orWhere($key, $match);

        return $this;
    }

    /**
     * Test this route on Alert
     */
    public function test(Alert $alert): bool
    {
        return ($this->criteria && $this->criteria->matches($alert));
    }

    /**
     * {@inheritDoc} Route Alert to destination
     */
    public function route(Alert $alert): ?PromiseInterface
    {
        if (!$this->destination) {
            return \React\Promise\resolve([]);
        }
        return $this->destination->route($alert);
    }

    public function getDestination(): ?RoutableInterface
    {
        return $this->destination;
    }

    public function setDestination(?RoutableInterface $destination = null): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getCriteria(): ?Criteria
    {
        return $this->criteria;
    }

    public function setCriteria(?Criteria $criteria): self
    {
        $this->criteria = $criteria;

        return $this;
    }

    public function __toString(): string
    {
        return 'criteria=' . (string)$this->criteria . '; ' .
            'destination=('.(\is_null($this->destination) ? 'NULL' : (string)$this->destination).')';
    }
}
