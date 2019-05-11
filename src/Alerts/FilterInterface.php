<?php
namespace SeanKndy\AlertManager\Alerts;

interface FilterInterface
{
    /**
     * Return true if $alert is filterable, false if acceptable.
     *
     * @param Alert $alert Alert to examine
     *
     * @return bool
     */
    public function isFiltered(Alert $alert) : bool;
}
