<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;

class AttrRoute extends AbstractRoute
{
    private $equals = [];
    private $regexMatchers = [];

    /**
     * {@inheritDoc} Must match all matchers defined (AND not OR)
     */
    protected function matches(Alert $alert) : bool
    {
        $attributes = $alert->getAttributes();
        foreach ($this->equals as $key => $equal) {
            if (!isset($attributes[$key])) {
                return false;
            }
            if (\is_array($equal) && !\in_array($attributes[$key], $equal)) {
                return false;
            }
            if ($attributes[$key] != $equal) {
                return false;
            }
        }
        foreach ($this->regexMatchers as $key => $regex) {
            if (!isset($attributes[$key]) || !\preg_match($regex, $attributes[$key])) {
                return false;
            }
        }
        return true;
    }

    public static function define($criteria, ?RoutableInterface $destination = null) : AbstractRoute
    {
        $route = new self($destination);

        $regex = $equals = [];
        foreach ($criteria as $key => $val) {
            if (substr($key, 0, 6) == 'regex:') {
                $regex[substr($key, 6)] = $val;
            } else {
                $equals[$key] = $val;
            }
        }
        $route->setEquals($equals);
        $route->setRegex($regex);

        return $route;
    }

    public function setEquals(array $equals = [])
    {
        $this->equals = $equals;

        return $this;
    }

    public function setRegex(array $regex = [])
    {
        $this->regex = $regex;

        return $this;
    }
}
