<?php
namespace SeanKndy\AlertManager\Receivers;

use React\Promise\PromiseInterface;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Routing\RoutableInterface;
use SeanKndy\AlertManager\Scheduling\ScheduleInterface;

abstract class AbstractReceiver implements RoutableInterface
{
    /**
     * Schedules determing when the receiver is active
     * An empty schedule means always on-call/active
     * @var ScheduleInterface[]
     */
    protected $schedules = [];
    /**
     * How many seconds after initial notify to continually re-notify.
     * @var int
     */
    protected $repeatInterval = 86400;
    /**
     * Receive recovered alerts?
     * @var bool
     */
    protected $receiveRecoveries = true;
    /**
     * Alert delay - initial time for receiver to refuse an alert
     * @var int
     */
    protected $alertDelay = 10;
    /**
     * @var FilterInterface[]
     */
    protected $filters = [];

    /**
     * Receive an Alert to act on it.
     *
     * @param Alert $alert
     *
     * @return void
     */
    abstract public function receive(Alert $alert) : PromiseInterface;

    /**
     * {@inheritDoc} Implement RoutableInterface by dispatching the Alert
     * to this Receiver.
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        if (!$this->isReceivable($alert)) {
            return null;
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
        // never receive alerts if off schedule
        if (!$this->isActivelyScheduled()) {
            return false;
        }
        // do not receive alert if it matches filter
        foreach ($this->filters as $filter) {
            if ($filter->isFiltered($alert)) {
                return false;
            }
        }

        if ($alert->isRecovered()) {
            // only send recovery if:
            // 1) receiveRecoveries is ON
            // 2) receiver received the active form of alert already
            return $this->receiveRecoveries &&
                $alert->getReceiverTransactionTime($this);
        }

        // only allow alert if delay time has elapsed since alert creation
        $minTime = $alert->getCreatedAt() + $this->alertDelay;
        if (\time() >= $minTime) {
            $lastReceivedTime = $alert->getReceiverTransactionTime($this);
            if ($lastReceivedTime) {
                // do not allow alert that has already been received
                // and interval has not elapsed
                if ($this->repeatInterval <= 0 ||
                    $lastReceivedTime+$this->repeatInterval > \time()) {
                    return false;
                }
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
        if (!$this->schedules) {
            return true;
        }

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

    /**
     * Add FilterInterface for this receiver
     *
     * @param FilterInterface $filter
     *
     * @return self
     */
    public function addFilter(FilterInterface $filter)
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Clear filters
     *
     * @return self
     */
    public function clearFilters()
    {
        $this->filters = [];

        return $this;
    }

    /**
     * String representation
     *
     * @return string
     */
    public function __toString()
    {
        return 'num-schedules=' . \count($this->schedules) . '; ' .
            'repeat-interval=' . $this->repeatInterval . 'sec; ' .
            'alert-delay=' . $this->alertDelay . 'sec; ' .
            'num-filters=' . \count($this->filters);
    }
}
