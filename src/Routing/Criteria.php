<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;

class Criteria
{
    const AND = 'AND';
    const OR  = 'OR';

    /**
     * @var array
     */
    private $criteria = [];
    /**
     * @var string
     */
    private $logic = self::AND;

    public function __construct(string $logic = self::AND)
    {
        $this->logic = $logic;
    }

    /**
     * Add criteria
     *
     * @param string\Criteria $key Either attribute key or another
     *      Criteria
     * @param mixed $match Value for $key to match or null if key is Criteria
     *
     * @return self
     */
    public function add($key, $match = null)
    {
        if ($key instanceof self) {
            $this->criteria[] = $key;
        } else {
            $this->criteria[] = [$key => $match];
        }

        return $this;
    }

    /**
     * @return Criteria
     */
    public function where($key, $match = null)
    {
        if (\is_callable($key)) {
            $criteria = (new self())
                ->add($this);
            $newCriteria = new self();
            $newCriteria = $key($newCriteria);
            return $criteria->add($newCriteria);
        } else {
            if ($this->isOr()) {
                $criteria = (new self())
                    ->add($this)
                    ->add($newCriteria = new self());
                $newCriteria->add($key, $match);
                return $criteria;
            }

            $this->add($key, $match);
            return $this;
        }
    }

    /**
     * @return Criteria
     */
    public function orWhere($key, $match = null)
    {
        if (\is_callable($key)) {
            $criteria = (new self(self::OR))
                ->add($this);
            $newCriteria = new self();
            $newCriteria = $key($newCriteria);
            return $criteria->add($newCriteria);
        } else {
            if ($this->isOr() || ($this->isAnd() && \count($this->criteria) == 1)) {
                $this->logic = self::OR;
                $this->add($key, $match);
                return $this;
            }

            $criteria = (new self(self::OR))
                ->add($this)
                ->add($newCriteria = new self());
            $newCriteria->add($key, $match);
            return $criteria;
        }
    }

    /**
     * Does $alert match this Criteria?
     *
     * @return bool
     */
    public function matches(Alert $alert)
    {
        if (!$this->criteria)
            return false;

        $attributes = $alert->getAttributes();

        foreach ($this->criteria as $criteria) {
            if ($criteria instanceof self) {
                if ($criteria->matches($alert)) {
                    if ($this->isOr()) {
                        return true;
                    }
                } else if ($this->isAnd()) {
                    return false;
                }
            } else {
                $key = \key($criteria);
                $match = \current($criteria);

                $regex = false;
                if (substr($key, 0, 6) == 'regex:') {
                    $key = substr($key, 6);
                    $regex = true;
                }

                if ($this->isAnd() && !isset($attributes[$key])) {
                    return false;
                }

                if ($regex) {
                    if (\preg_match($match, $attributes[$key])) {
                        if ($this->isOr()) {
                            return true;
                        }
                    } else if ($this->isAnd()) {
                        return false;
                    }
                } else {
                    if (!\is_array($match)) {
                        $match = [$match];
                    }
                    if (\in_array($attributes[$key], $match)) {
                        if ($this->isOr()) {
                            return true;
                        }
                    } else if ($this->isAnd()) {
                        return false;
                    }
                }
            }
        }

        return $this->isOr() ? false : true;
    }

    public function isOr()
    {
        return $this->logic == self::OR;
    }

    public function isAnd()
    {
        return $this->logic == self::AND;
    }
}
