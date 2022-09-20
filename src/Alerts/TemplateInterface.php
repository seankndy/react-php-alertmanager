<?php

namespace SeanKndy\AlertManager\Alerts;

interface TemplateInterface
{
    /**
     * Return brief (one-liner) description of $alert.
     *
     * @param Alert $alert
     *
     * @return string
     */
    public function brief(Alert $alert) : string;

    /**
     * Return detailed string for $alert.
     *
     * @param Alert $alert
     *
     * @return string
     */
    public function detail(Alert $alert) : string;
}
