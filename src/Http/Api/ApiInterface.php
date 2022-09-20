<?php

namespace SeanKndy\AlertManager\Http\Api;

interface ApiInterface
{
    /**
     * Define the HTTP routes for an API.
     */
    public function routes(\FastRoute\RouteCollector $routeCollector): void;
}