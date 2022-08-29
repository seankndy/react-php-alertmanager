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
     */
    protected ?\SplObjectStorage $schedules = null;
    /**
     * ScheduleInterface determining when the receiver should NOT be active
     * If any schedule here is active then it overrides the above schedules.
     */
    protected ?\SplObjectStorage $exclusionSchedules = null;
    /**
     * How many seconds after initial notify to continually re-notify
     * (if state != ACKNOWLEDGED)
     */
    protected int $repeatInterval = 86400;
    /**
     * Receive recovered alerts?
     */
    protected bool $receiveRecoveries = true;
    /**
     * Alert delay - initial time for receiver to refuse an alert
     *  0 = disabled
     * >0 = seconds past alert creation time before alert is receiveable
     * -1 = special flag meaning until the Alert's updated time is newer than
     *      it's created time, the alert remains un-receivable.
     *
     */
    protected int $alertDelay = -1;
    /**
     * Filters for this receiver.
     */
    protected ?\SplObjectStorage $filters = null;

    protected ?TemplateInterface $alertTemplate = null;

    public function __construct($id = null)
    {
        if ($id === null) {
            $this->id = Uuid::uuid4()->toString();
        } else {
            $this->id = $id;
        }
        $this->schedules = new \SplObjectStorage();
        $this->exclusionSchedules = new \SplObjectStorage();
        $this->filters = new \SplObjectStorage();
    }

    /**
     * {@inheritDoc}
     */
    public function route(Alert $alert): ?PromiseInterface
    {
        if (!$this->isReceivable($alert)) {
            return null;
        }
        return $alert->dispatch($this);
    }

    /**
     * {@inheritDoc}
     */
    public function isReceivable(Alert $alert): bool
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

    public function receiverId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    public function addSchedule(ScheduleInterface $schedule): self
    {
        $this->schedules->attach($schedule);

        return $this;
    }

    public function addExclusionSchedule(ScheduleInterface $schedule): self
    {
        $this->exclusionSchedules->attach($schedule);

        return $this;
    }

    public function removeSchedule(ScheduleInterface $schedule): self
    {
        $this->schedules->detach($schedule);

        return $this;
    }

    public function removeExclusionSchedule(ScheduleInterface $schedule): self
    {
        $this->exclusionSchedules->detach($schedule);

        return $this;
    }

    /**
     * Set all schedules with an array
     *
     * @param ScheduleInterface[] $schedules
     */
    public function setSchedules(array $schedules): self
    {
        $this->schedules = new \SplObjectStorage();
        foreach ($schedules as $schedule) {
            $this->addSchedule($schedule);
        }

        return $this;
    }

    /**
     * Set all exclusion schedules via an array
     *
     * @param ScheduleInterface[] schedules
     */
    public function setExclusionSchedules(array $schedules): self
    {
        $this->exclusionSchedules = new \SplObjectStorage();
        foreach ($schedules as $schedule) {
            $this->addExclusionSchedule($schedule);
        }

        return $this;
    }

    /**
     * Get all schedules as an array.
     *
     * @return ScheduleInterface[]
     */
    public function getSchedules(): array
    {
        return \iterator_to_array($this->schedules);
    }

    /**
     * Get all exclusion schedules as array.
     *
     * @return ScheduleInterface[]
     */
    public function getExclusionSchedules(): array
    {
        return \iterator_to_array($this->exclusionSchedules);
    }

    public function setRepeatInterval(int $repeatInterval): self
    {
        $this->repeatInterval = $repeatInterval;

        return $this;
    }

    public function getRepeatInterval(): int
    {
        return $this->repeatInterval;
    }

    public function setReceiveRecoveries(bool $flag): self
    {
        $this->receiveRecoveries = $flag;

        return $this;
    }

    public function receiveRecoveries(): bool
    {
        return $this->receiveRecoveries;
    }

    /**
     * Determine if this receiver is currently on-call/scheduled.
     */
    public function isActivelyScheduled(): bool
    {
        // first check if any exclusion schedules are active.
        // if they are, then user is not active.
        foreach ($this->exclusionSchedules as $schedule) {
            if ($schedule->isActive()) {
                return false;
            }
        }

        // if no schedules exist, user is always active.
        if (\count($this->schedules) == 0) {
            return true;
        }

        // check if any user schedules are active and if so
        // then the user is active.
        foreach ($this->schedules as $schedule) {
            if ($schedule->isActive()) {
                return true;
            }
        }

        // user not active
        return false;
    }

    public function setAlertDelay(int $alertDelay): self
    {
        $this->alertDelay = $alertDelay;

        return $this;
    }

    public function getAlertDelay(): int
    {
        return $this->alertDelay;
    }

    public function addFilter(FilterInterface $filter): self
    {
        $this->filters->attach($filter);

        return $this;
    }

    public function removeFilter(FilterInterface $filter): self
    {
        $this->filters->detach($filter);

        return $this;
    }

    public function clearFilters(): self
    {
        $this->filters = new \SplObjectStorage();

        return $this;
    }

    /**
     * Get the filters as an array.
     *
     * @return FilterInterface[]
     */
    public function getFilters(): array
    {
        return \iterator_to_array($this->filters);
    }

    public function setAlertTemplate(?TemplateInterface $alertTemplate): self
    {
        $this->alertTemplate = $alertTemplate;

        return $this;
    }

    public function getAlertTemplate(): ?TemplateInterface
    {
        return $this->alertTemplate;
    }

    public function __toString(): string
    {
        return 'id=' . $this->id . '; num-schedules=' . \count($this->schedules) . '; ' .
            'num-exclusion-schedules=' . \count($this->exclusionSchedules) . '; ' .
            'receive-recoveries=' . ($this->receiveRecoveries ? 'TRUE' : 'FALSE') . '; ' .
            'repeat-interval=' . $this->repeatInterval . 'sec; ' .
            'alert-delay=' . $this->alertDelay . 'sec; ' .
            'num-filters=' . \count($this->filters);
    }

}
