<?php
namespace SeanKndy\AlertManager\Receivers;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;

abstract class AbstractReceiver implements RoutableInterface
{
    /**
     * Schedules determing when the receiver is active
     * @var ScheduleInterface[]
     */
    private $schedules = [];
    /**
     * How many seconds after initial notify to continually re-notify.
     * @var int
     */
    private $repeatInterval = 86400
    /**
     * Receive recovered alerts?
     * @var bool
     */
    private $receiveRecoveries = true;
    /**
     * Alert delay
     * @var int
     */
    private $alertDelay = 310;

    /**
     * Receive an Alert to act on it.
     *
     * @param Alert $alert
     *
     * @return void
     */
    abstract public function receive(Alert $alert) : PromiseInterface;

    /**
     * Implement RoutableInterface by dispatching the alert to this Receiver.
     *
     */
    public function route(Alert $alert) : PromiseInterface
    {
        if (!$this->isReceivable($alert)) {
            return \React\Promise\resolve([]);
        }
        return $alert->dispatch($this);
    }

    /**
     * Determine if this Receiver is ready/capable of receiving for Alert $alert
     *
     * @param Alert $alert
     *
     * @return bool
     */
    public function isReceivable(Alert $alert)
    {
        if ($alert->isRecovered() && !$this->receiveRecoveries) {
            return false;
        }

        $minTime = $alert->getCreatedAt() + $this->alertDelay;
        if ($this->isActivelyScheduled() && $minTime >= \time())) {
            $lastReceivedTime = $alert->getReceiverTransactionTime($this);
            if ($lastReceivedTime && $lastReceivedTime+$this->repeatInterval < \time()) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Add ScheduleInterface for this Receiver
     *
     * @return self
     */
    public function addSchedule(ScheduleInterface $schedule)
    {
        $this->schedules[] = $schedule;

        return $this;
    }

    /**
     * Set the value of Schedules determing when the receiver is active
     *
     * @param ScheduleInterface[] schedules
     *
     * @return self
     */
    public function setSchedules(array $schedules)
    {
        $this->schedules = $schedules;

        return $this;
    }

    /**
     * Get the value of Schedules determing when the receiver is active
     *
     * @return ScheduleInterface[]
     */
    public function getSchedules()
    {
        return $this->schedules;
    }

    /**
     * Set the value of repeat interval
     *
     * @param int repeatInterval
     *
     * @return self
     */
    public function setRepeatInterval(int $repeatInterval)
    {
        $this->repeatInterval = $repeatInterval;

        return $this;
    }

    /**
     * Get the value of repeat interval
     * @return int
     */
    public function getRepeatInterval()
    {
        return $this->repeatInterval;
    }

    /**
     * Set the value of receive recoveries
     *
     * @param bool receiveRecoveries
     *
     * @return self
     */
    public function setReceiveRecoveries(bool $flag)
    {
        $this->receiveRecoveries = $flag;

        return $this;
    }

    /**
     * Get the value of Send recoveries
     *
     * @return bool
     */
    public function receiveRecoveries()
    {
        return $this->receiveRecoveries;
    }

    /**
     * Determine if this receiver is currently on-call/scheduled.
     *
     * @return bool
     */
    public function isActivelyScheduled()
    {
        foreach ($this->schedules as $schedule) {
            if ($schedule->isActive()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set the value of Alert delay
     *
     * @param int alertDelay
     *
     * @return self
     */
    public function setAlertDelay(int $alertDelay)
    {
        $this->alertDelay = $alertDelay;

        return $this;
    }

    /**
     * Get the value of Alert delay
     *
     * @return int
     */
    public function getAlertDelay()
    {
        return $this->alertDelay;
    }
}
