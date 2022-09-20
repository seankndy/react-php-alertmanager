<?php

namespace SeanKndy\AlertManager\Http\Api;

interface DefinesRoutes
{
    /**
     * Define the HTTP routes for an API.
     */
    public function defineRoutes(\FastRoute\RouteCollector $routeCollector): void;
}