<?php
namespace SeanKndy\AlertManager\Routing;

class RouteCriteria
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

    public function __construct(string $logic)
    {
        $this->logic = $logic;
    }

    public function add($key, $match = null)
    {
        if ($key instanceof self) {
            $this->criteria[] = $key;
        } else {
            if (substr($key, 0, 6) == 'regex:') {
                $this->criteria[] = [substr($key, 6) => $match];
            } else {
                $this->criteria[] = [$key => $match];
            }
        }
    }

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
                list($key,$match) = $criteria;

                if ($this->isAnd() && !isset($attributes[$key])) {
                    return false;
                }

                if (substr($key, 0, 6) == 'regex:') {
                    $key = substr($key, 6);
                    if (\preg_match($match, $attributes[$key])) {
                        if ($this->isOr()) {
                            return true;
                        }
                    } else if ($this->isAnd()) {
                        return false;
                    }
                } else {
                    if (\is_array($match)) {
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

        return true;
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
