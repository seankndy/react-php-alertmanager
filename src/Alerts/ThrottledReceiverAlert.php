<?php

namespace SeanKndy\AlertManager\Alerts;

/**
 * Alert representing that a Receiver has been throttled.
 */
class ThrottledReceiverAlert extends Alert
{
    /**
     * @var Alert[]
     */
    public $alerts = [];

    /**
     * @var Alert[] $alerts Array of alerts that caused the throttling
     * @var int $expiresAt Time that the holddown will expire
     *
     */
    public function __construct(array $alerts, $expiresAt)
    {
        parent::__construct(
            'ALERTMANAGER_HOLDDOWN_ACTIVE',
            Alert::ACTIVE, ['expiresAt'=>$expiresAt], \time(), 0
        );

        $this->alerts = $alerts;
    }
}
