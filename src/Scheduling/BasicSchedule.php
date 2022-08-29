<?php

namespace SeanKndy\AlertManager\Scheduling;

use DateTime, DateTimeZone;
use DateInterval, DatePeriod;
use InvalidArgumentException;

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
     * Start of schedule, EPOCH timestamp
     */
    private int $startTime;
    /**
     * End of schedule, EPOCH timestamp
     */
    private int $endTime;
    /**
     * Timezone in which we're operating
     */
    private DateTimeZone $timezone;
    /**
     * Repeat frequency: self::FREQ_NONE, self::FREQ_DAILY or self::FREQ_WEEKLY
     */
    private int $repeatFrequency = self::FREQ_NONE;
    /**
     * Repeat interval for the above frequency
     */
    private int $repeatInterval = 0;


    /**
     * @param DateTimeZone|string $timezone
     * @throws InvalidArgumentException
     */
    public function __construct(int $startTime, int $endTime, $timezone)
    {
        if ($startTime >= $endTime) {
            throw new InvalidArgumentException('Start Time must be > End Time.');
        }

        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setRepeatFrequency(int $frequency): self
    {
        $validFrequencies = [self::FREQ_NONE, self::FREQ_DAILY, self::FREQ_WEEKLY];
        if (!\in_array($frequency, $validFrequencies)) {
            throw new InvalidArgumentException('Invalid frequency, must be one of [' .
                \implode(', ', $validFrequencies) . ']');
        }
        $this->repeatFrequency = $frequency;

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setRepeatInterval(int $interval): self
    {
        if ($interval < 0) {
            throw new InvalidArgumentException('Interval must be >= 0.');
        }
        $this->repeatInterval = $interval;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function isActive(int $atTime = 0): bool
    {
        if (!$atTime) {
            $atTime = (new DateTime('now', $this->timezone))->getTimestamp();
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
            $duration = $this->endTime - $this->startTime;

            $period = new DatePeriod(
                (new DateTime('now', $this->timezone))->setTimestamp($this->startTime),
                new DateInterval('P' . $this->repeatInterval . ($this->repeatFrequency == self::FREQ_WEEKLY ? 'W' : 'D')),
                (new DateTime('now', $this->timezone))->setTimestamp($atTime+86400)
            );

            foreach ($period as $startDateTime) {
                $endDateTime = clone $startDateTime;
                $endDateTime->modify('+ ' . $duration . ' seconds');

                // atTime is within start/end time
                if ($atTime >= $startDateTime->getTimestamp() && $atTime <= $endDateTime->getTimestamp()) {
                    return true;
                }
            }
        }

        return false;
    }

}
