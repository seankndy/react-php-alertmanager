<?php
namespace SeanKndy\AlertManager\Alerts;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Receivers\AbstractReceiver;
/**
 * Alert representing that a Receiver has been throttled.
 */
class ThrottledReceiverAlert extends Alert
{
    public function __construct($expiresAt)
    {
        parent::__construct(
            'ALERTMANAGER_HOLDDOWN_ACTIVE',
            Alert::ACTIVE, ['expiresAt'=>$expiresAt], \time(), 0
        );
    }
}
