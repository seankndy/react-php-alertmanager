<?php

namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\AggregatedAlert;
use React\Promise\PromiseInterface;
use Carbon\Carbon;

class Aggregator extends ReceiverDecorator
{
    /**
     * The aggregation time interval in minutes.
     * Alerts received in this interval are aggregated into one alert.
     */
    private int $interval = 15;

    /**
     * The time that the current interval started.
     * This is initially set when an alert is received and this value was null.
     */
    private ?Carbon $intervalStart = null;

    /**
     * Alerts that have been aggregated within the interval.
     * @var \SplObjectStorage
     */
    private \SplObjectStorage $alerts;

    public function __construct(ReceivableInterface $receiver)
    {
        parent::__construct($receiver);

        $this->alerts = new \SplObjectStorage();
    }

    public function isReceivable(Alert $alert): bool
    {
        if ($this->receiver->isReceivable($alert)) {
            // alert is receivable by the underlying receiver, so track the alert
            $this->alerts->attach($alert);
            return true;
        } else if ($this->alerts->contains($alert)) {
            // alert is not receivable, but it was before since it was tracked
            // remove it from tracking
            $this->alerts->detach($alert);

            // if alert tracking now empty, then clear the current interval
            if ($this->alerts->count() === 0) {
                $this->clear();
            }
        }

        return false;
    }

    public function receive(Alert $alert): PromiseInterface
    {
        if ($this->intervalStart === null) {
            $this->intervalStart = Carbon::now();
        }

        if ($this->intervalStart->diffInMinutes(Carbon::now()) >= $this->interval) {
            // send aggregated alert to the receiver
            $promise = $this->receiver->receive(new AggregatedAlert(
                \iterator_to_array($this->alerts)
            ));

            // logDispatch all the alerts for $this->resolveReceiver()
            foreach ($this->alerts as $alert) {
                $alert->logDispatch($this->resolveReceiver());
            }

            // clear the data for this interval
            $this->clear();

            return $promise;
        }

        return \React\Promise\resolve([]);
    }

    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    protected function clear(): void
    {
        $this->alerts = new \SplObjectStorage();
        $this->intervalStart = null;
    }
}
