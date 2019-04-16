<?php
namespace SeanKndy\AlertManager\Routing;

use SeanKndy\AlertManager\Alerts\Alert;

class LabelRoute extends AbstractRoute
{
    private $matchers = [];

    /**
     * {@inheritDoc} Must match all $matchers defined (AND not OR)
     */
    protected function matches(Alert $alert) : bool
    {
        $labels = $alert->getLabels();
        foreach ($this->matchers as $key => $regex) {
            if (!isset($labels[$key]) || !\preg_match($regex, $labels[$key])) {
                return false;
            }
        }
        return true;
    }

    public static function define($criteria, RoutableInterface $destination) : AbstractRoute
    {
        $route = new self($destination);
        $route->setMatchers($criteria);

        return $route;
    }

    public function setMatchers(array $matchers)
    {
        $this->matchers = $matchers;

        return $this;
    }
}
