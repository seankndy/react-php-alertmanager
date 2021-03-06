<?php
namespace SeanKndy\AlertManager\Receivers;

use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Alerts\FilterInterface;
use SeanKndy\AlertManager\Alerts\TemplateInterface;
use SeanKndy\AlertManager\Scheduling\ScheduleInterface;
use React\Promise\PromiseInterface;
use Ramsey\Uuid\Uuid;
/**
 * Provides a base set of Receiver functions such as scheduling, repeat intervals,
 * alert delays and filtering.
 *
 */
abstract class AbstractReceiver implements ReceivableInterface
{
    /**
     * Identifier for the receiver
     * @var mixed
     */
    protected $id;
    /**
     * ScheduleInterface determining when the receiver is active
     * An empty schedule means always on-call/active
     * @var \SplObjectStorage
     */
    protected $schedules = null;
    /**
     * How many seconds after initial notify to continually re-notify
     * (if state != ACKNOWLEDGED)
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
     *  0 = disabled
     * >0 = seconds past alert creation time before alert is receiveable
     * -1 = special flag meaning until the Alert's updated time is newer than
     *      it's created time, the alert remains un-receivable.
     *
     * @var int
     */
    protected $alertDelay = -1;
    /**
     * @var \SplObjectStorage
     */
    protected $filters = null;
    /**
     * @var TemplateInterface
     */
    protected $alertTemplate = null;

    public function __construct($id = null)
    {
        if ($id === null) {
            $this->id = Uuid::uuid4()->toString();
        } else {
            $this->id = $id;
        }
        $this->schedules = new \SplObjectStorage();
        $this->filters = new \SplObjectStorage();
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert) : ?PromiseInterface
    {
        if (!$this->isReceivable($alert)) {
            return null;
        }
        return $alert->dispatch($this);
    }

    /**
     * {@inheritDoc}
     */
    public function isReceivable(Alert $alert) : bool
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

        $dispatchLog = $alert->getDispatchLogForReceiver($this);

        if ($alert->isRecovered()) {
            // only send recovery if:
            // 1) receiveRecoveries is ON
            // 2) receiver received the active form of alert already
            // 3) receiver has not received recovered form already
            return $this->receiveRecoveries &&
                isset($dispatchLog[Alert::ACTIVE])
                && !isset($dispatchLog[Alert::RECOVERED]);
        }

        // don't send active alerts if the alert state is ACKNOWLEDGED
        if ($alert->isAcknowledged()) {
            return false;
        }

        // see property docblock for explanation of alertDelay values
        if ($this->alertDelay == -1) {
            $meetsDelay = $alert->getUpdatedAt() > $alert->getCreatedAt();
        } else {
            $minTime = $alert->getCreatedAt() + $this->alertDelay;
            $meetsDelay = \time() >= $minTime;
        }

        // only allow alert if delay time has been met
        if ($meetsDelay) {
            $lastReceivedTime = $dispatchLog[$alert->getState()] ?? 0;
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
     * {@inheritDoc}
     */
    public function receiverId()
    {
        return $this->id;
    }

    /**
     * Get the value of ID
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of ID
     *
     * @param mixed $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Add ScheduleInterface for this Receiver
     *
     * @return self
     */
    public function addSchedule(ScheduleInterface $schedule)
    {
        $this->schedules->attach($schedule);

        return $this;
    }

    /**
     * Remove ScheduleInterface
     *
     * @return self
     */
    public function removeSchedule(ScheduleInterface $schedule)
    {
        $this->schedules->detach($schedule);

        return $this;
    }

    /**
     * Set all schedules via an array
     *
     * @param ScheduleInterface[] schedules
     *
     * @return self
     */
    public function setSchedules(array $schedules)
    {
        $this->schedules = new \SplObjectStorage();
        foreach ($schedules as $schedule) {
            $this->addSchedule($schedule);
        }

        return $this;
    }

    /**
     * Get the value of Schedules determing when the receiver is active
     *
     * @return ScheduleInterface[]
     */
    public function getSchedules()
    {
        return \iterator_to_array($this->schedules);
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
        if (\count($this->schedules) == 0) {
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
     * Add FilterInterface
     *
     * @param FilterInterface $filter
     *
     * @return self
     */
    public function addFilter(FilterInterface $filter)
    {
        $this->filters->attach($filter);

        return $this;
    }

    /**
     * Remove FilterInterface
     *
     * @param FilterInterface $filter Filter to remove
     *
     * @return self
     */
    public function removeFilter(FilterInterface $filter)
    {
        $this->filters->detach($filter);

        return $this;
    }

    /**
     * Clear filters
     *
     * @return self
     */
    public function clearFilters()
    {
        $this->filters = new \SplObjectStorage();

        return $this;
    }

    /**
     * Get the filters for this receiver
     *
     * @return FilterInterface[]
     */
    public function getFilters()
    {
        return \iterator_to_array($this->filters);
    }

    /**
     * Set the value of $alertTemplate
     *
     * @param TemplateInterface $alertTemplate
     *
     * @return self
     */
    public function setAlertTemplate(TemplateInterface $alertTemplate)
    {
        $this->alertTemplate = $alertTemplate;

        return $this;
    }

    /**
     * Get the value of $alertTemplate
     *
     * @return TemplateInterface
     */
    public function getAlertTemplate()
    {
        return $this->alertTemplate;
    }

    /**
     * String representation
     *
     * @return string
     */
    public function __toString()
    {
        return 'id=' . $this->id . '; num-schedules=' . \count($this->schedules) . '; ' .
            'receive-recoveries=' . ($this->receiveRecoveries ? 'TRUE' : 'FALSE') . '; ' .
            'repeat-interval=' . $this->repeatInterval . 'sec; ' .
            'alert-delay=' . $this->alertDelay . 'sec; ' .
            'num-filters=' . \count($this->filters);
    }

}
