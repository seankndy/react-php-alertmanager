<?php

namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;

class Criteria
{
    const AND = 'AND';
    const OR  = 'OR';

    private array $criteria;

    private string $logic;

    public function __construct(string $logic = self::AND)
    {
        $this->criteria = [];
        $this->logic = $logic;
    }

    /**
     * Add criteria
     *
     * @param string|Criteria $key Either attribute key or another Criteria
     * @param mixed $match Value for $key to match or null if key is Criteria
     */
    public function add($key, $match = null): self
    {
        if ($key instanceof self) {
            $this->criteria[] = $key;
        } else {
            $this->criteria[] = [$key => $match];
        }

        return $this;
    }

    public function where($key, $match = null): Criteria
    {
        if (\is_callable($key)) {
            $criteria = new self();
            if ($this->criteria) {
                $criteria->add($this);
            }
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

    public function orWhere($key, $match = null): Criteria
    {
        if (\is_callable($key)) {
            $criteria = new self(self::OR);
            if ($this->criteria) {
                $criteria->add($this);
            }
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
     * Does $alert match $this Criteria?
     */
    public function matches(Alert $alert): bool
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

    public function isOr(): bool
    {
        return $this->logic == self::OR;
    }

    public function isAnd(): bool
    {
        return $this->logic == self::AND;
    }

    /**
     * String representation of the criteria, similar to SQL WHERE syntax
     */
    public function __toString(): string
    {
        if (!$this->criteria)
            return '';

        $str = '(';
        $logicSep = '';
        foreach ($this->criteria as $criteria) {
            if ($criteria instanceof self) {
                $str .= ($logicSep ? ' '.$logicSep.' ' : '') .
                    (string)$criteria;
            } else {
                $key = \key($criteria);
                $match = \current($criteria);

                $regex = false;
                if (substr($key, 0, 6) == 'regex:') {
                    $key = substr($key, 6);
                    $regex = true;
                }

                if ($regex) {
                    $str .= ($logicSep ? ' '.$logicSep.' ' : '') .
                        $key . '=' . $match;
                } else {
                    if (!\is_array($match)) {
                        $str .= ($logicSep ? ' '.$logicSep.' ' : '') .
                            $key . '=' . $match;
                    } else {
                        $str .= ($logicSep ? ' '.$logicSep.' ' : '') .
                            $key . ' IN(' . \implode(',', $match) . ')';
                    }
                }
            }
            $logicSep = $this->logic;
        }
        $str .= ')';

        return $str;
    }
}
