<?php
namespace SeanKndy\AlertManager\Scheduling;
/**
 * Basic scheduling that supports daily or weekly repetition.
 *
 */
class BasicSchedule implements ScheduleInterface
{
    const FREQ_NONE = 0;
    const FREQ_DAILY = 1;
    const FREQ_WEEKLY = 7;

    /**
     * Start of schedule, timestamp
     * @var int
     */
    private $startTime;
    /**
     * End of schedule
     * @var int
     */
    private $endTime;
    /**
     * Repeat frequency, FREQ_NONE, FREQ_DAILY or FREQ_WEEKLY
     * @var int
     */
    private $repeatFrequency = self::FREQ_NONE;
    /**
     * Repeat interval for the above frequency
     * @var int
     */
    private $repeatInterval = 0;


    public function __construct(int $startTime, int $endTime)
    {
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public function setStartTime(int $time)
    {
        $this->startTime = $time;
    }

    public function setEndTime(int $time)
    {
        $this->endTime = $time;
    }

    public function setRepeatFrequency(int $freq)
    {
        if (!\in_array($freq, [self::FREQ_NONE,self::FREQ_DAILY,self::FREQ_WEEKLY])) {
            throw new \InvalidArgumentException("Invalid frequency.");
        }
        $this->repeatFrequency = $freq;
        return $this;
    }

    public function setRepeatInterval(int $interval)
    {
        $this->repeatInterval = $interval;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isActive(int $atTime = 0) : bool
    {
        if (!$atTime) {
            $atTime = \time();
        }

        // atTime before start time
        if ($atTime < $this->startTime) {
            return false;
        }

        // atTime is within start/end time
        if ($atTime >= $this->startTime && $atTime <= $this->endTime) {
            return true;
        }

        // atTime is not within start/end time, check for repetition
        if ($this->repeatFrequency != self::FREQ_NONE && $this->repeatInterval > 0) {
            // determine days since the start of schedule
            $daysSinceStart = floor(($atTime - $this->startTime)/86400);
            // divide that by our frequency (either daily(1) or weekly(7))
            $numOccurrences = floor($daysSinceStart/$this->repeatFrequency);

            // if that divides cleanly, then $atTime matches
            if ($numOccurrences % $this->repeatInterval == 0) {
                $newStartDate = $this->startTime + ($numOccurrences * $this->repeatFrequency * 86400);

                // make a new BasicSchedule with new time frames
                $newStartTime = \mktime(
                    \date('H', $this->startTime),
                    \date('i', $this->startTime),
                    \date('s', $this->startTime),
                    \date('n', $newStartDate),
                    \date('j', $newStartDate),
                    \date('Y', $newStartDate)
                );
                $newEndTime = $newStartTime + ($this->endTime - $this->startTime);

                return (new self($newStartTime, $newEndTime))->isActive($atTime);
            }
        }

        return false;
    }
}
