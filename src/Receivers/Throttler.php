<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\ThrottledReceiverAlert;
use React\Promise\PromiseInterface;

class Throttler extends ReceiverDecorator
{
    /**
     * Interval in which to apply throttling rules.
     * @var int
     */
    protected $interval = 60;
    /**
     * Time that the interval started
     * @var int
     */
    protected $startTime = 0;
    /**
     * Maximum receiver calls within $interval time
     * @var int
     */
    protected $hitThreshold = 15;
    /**
     * Last time a hit occurred
     * @var int
     */
    protected $lastHitTime = 0;
    /**
     * Number of hits within the interval
     * @var int
     */
    protected $hitCount = 0;
    /**
     * Hold down time after throttling occurs.
     * @var int
     */
    protected $holdDown = 1800;
    /**
     * Time holddown started
     * @var int
     */
    protected $holdDownStartTime = 0;
    /**
     * A receiver to send a special notification (Alert) to notify that the
     * original receiver has been throttled.
     * @var AbstractReceiver
     */
    protected $onHoldDownReceiver = null;

    /**
     * {@inheritDoc}
     */
    public function receive(Alert $alert) : PromiseInterface
    {
        if ($this->holdDownStartTime) {
            if (\time() - $this->holdDownStartTime < $this->holdDown) {
                // we're under hold down, return silently
                return \React\Promise\resolve([]);
            } else {
                // holddown expired, reset to 0
                $this->holdDownStartTime = 0;
                $this->startTime = 0;
            }
        }

        // if no start time (first hit ever) or if it's been over $this->interval
        // seconds since the last hit, its time to reset start/hits.
        if (!$this->startTime || \time()-$this->lastHitTime > $this->interval) {
            $this->startTime = \time();
            $this->hitCount = 0;
        }

        $this->lastHitTime = \time();
        $this->hitCount++;

        // has hit count exceeed threshold?
        if ($this->hitCount >= $this->hitThreshold) {
            $this->holdDownStartTime = \time();
            if ($this->onHoldDownReceiver) {
                $this->onHoldDownReceiver->receive(new ThrottledReceiverAlert(
                    $this->holdDownStartTime+$this->holdDown
                ));
            }
            return \React\Promise\resolve([]);
        }

        return $this->receiver->receive($alert);
    }

    /**
     * Get the value of Interval.
     *
     * @return int
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * Set the value of Interval
     *
     * @param int interval
     *
     * @return self
     */
    public function setInterval(int $interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * Get the value of hit threshold
     *
     * @return int
     */
    public function getHitThreshold()
    {
        return $this->hitThreshold;
    }

    /**
     * Set the value of hit threshold
     *
     * @param int $threshold
     *
     * @return self
     */
    public function setHitThreshold(int $threshold)
    {
        $this->hitThreshold = $threshold;

        return $this;
    }

    /**
     * Get the value of Hold down time after throttling occurs.
     *
     * @return int
     */
    public function getHoldDown()
    {
        return $this->holdDown;
    }

    /**
     * Set the value of Hold down time after throttling occurs.
     *
     * @param int holdDown
     *
     * @return self
     */
    public function setHoldDown(int $holdDown)
    {
        $this->holdDown = $holdDown;

        return $this;
    }

    /**
     * Get value of onHoldDownReceiver
     *
     * @return AbstractReceiver|null
     */
    public function getOnHoldDownReceiver(AbstractReceiver $receiver)
    {
        return $this->onHoldDownReceiver;
    }

    /**
     * Set value of onHoldDownReceiver
     *
     * @param AbstractReceiver $receiver
     *
     * @return self
     */
    public function setOnHoldDownReceiver(AbstractReceiver $receiver)
    {
        $this->onHoldDownReceiver = $receiver;

        return $this;
    }
}
