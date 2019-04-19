<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;

interface FilterInterface
{
    /**
     * Return true if $alert is filterable, false if acceptable.
     *
     * @param Alert $alert Alert to examine
     *
     * @return bool
     */
    public class isFiltered(Alert $alert) : bool;
}
