<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\ThrottledReceiverAlert;
use React\Promise\PromiseInterface;
use Carbon\Carbon;

class Throttler extends ReceiverDecorator
{
    /**
     * Interval in which to apply throttling rules.
     */
    protected int $interval = 60;
    /**
     * Time that the interval started
     */
    protected int $startTime = 0;
    /**
     * Maximum receiver calls within $interval time
     */
    protected int $hitThreshold = 15;
    /**
     * List of alerts within the past interval
     * @var Alert[]
     */
    protected array $alertsInInterval = [];
    /**
     * Last time a hit occurred
     */
    protected int $lastHitTime = 0;
    /**
     * Hold down time after throttling occurs.
     */
    protected int $holdDown = 1800;
    /**
     * Time holddown started
     */
    protected int $holdDownStartTime = 0;
    /**
     * A receiver to send a special notification (ThrottledReceiverAlert) to
     * notify that the original receiver has been throttled.
     */
    protected ?ReceivableInterface $onHoldDownReceiver = null;

    public function receive(Alert $alert): PromiseInterface
    {
        $now = Carbon::now()->timestamp;

        if ($this->holdDownStartTime) {
            if ($now - $this->holdDownStartTime < $this->holdDown) {
                // under holddown, just silently return
                $alert->logDispatch($this->resolveReceiver());
                return \React\Promise\resolve([]);
            } else {
                // holddown expired, reset to 0
                $this->holdDownStartTime = 0;
                $this->startTime = 0;
                $this->alertsInInterval = [];
            }
        }

        // if no start time (first hit ever) or if it's been over $this->interval
        // seconds since the last hit, its time to reset start/hits.
        if (!$this->startTime || $now-$this->lastHitTime > $this->interval) {
            $this->startTime = $now;
            $this->alertsInInterval = [];
        }

        $this->lastHitTime = $now;
        $this->alertsInInterval[] = $alert;

        // has hit count exceeed threshold?
        if (\count($this->alertsInInterval) >= $this->hitThreshold) {
            $this->holdDownStartTime = $now;
            if ($this->onHoldDownReceiver) {
                $this->onHoldDownReceiver->receive(new ThrottledReceiverAlert(
                    $this->alertsInInterval,
                    $this->holdDownStartTime+$this->holdDown
                ));
            }
            // we're now under hold down, so register the receiver in the alert
            // as if it was dispatched to the receiver, but silently consume the alert.
            $alert->logDispatch($this->resolveReceiver());
            return \React\Promise\resolve([]);
        }

        return $this->receiver->route($alert);
    }


    public function getInterval(): int
    {
        return $this->interval;
    }

    public function setInterval(int $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    public function getHitThreshold(): int
    {
        return $this->hitThreshold;
    }

    public function setHitThreshold(int $threshold): self
    {
        $this->hitThreshold = $threshold;

        return $this;
    }

    public function getHoldDown(): int
    {
        return $this->holdDown;
    }

    public function setHoldDown(int $holdDown): self
    {
        $this->holdDown = $holdDown;

        return $this;
    }

    public function getOnHoldDownReceiver(): ?ReceivableInterface
    {
        return $this->onHoldDownReceiver;
    }

    public function setOnHoldDownReceiver(?ReceivableInterface $receiver): self
    {
        $this->onHoldDownReceiver = $receiver;

        return $this;
    }
}
