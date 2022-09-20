<?php

namespace SeanKndy\AlertManager\Alerts;

class AggregatedAlert extends Alert
{
    /**
     * The alerts that have been aggregated over time.
     * @var Alert[]
     */
    public array $alerts;

    /**
     * @var Alert[] $alerts
     */
    public function __construct(array $alerts)
    {
        parent::__construct(
            'ALERTMANAGER_AGG_ALERT',
            Alert::ACTIVE
        );

        $this->alerts = $alerts;
    }
}
