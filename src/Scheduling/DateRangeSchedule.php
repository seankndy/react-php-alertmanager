<?php

namespace SeanKndy\AlertManager\Scheduling;

use InvalidArgumentException;

/**
 * A single absolute window of time, [start, end).
 *
 * This is the building block for one-off events: a holiday, a maintenance
 * window, a vacation you want to exclude.
 */
final class DateRangeSchedule implements SerializableScheduleInterface, TransitionalScheduleInterface
{
    private int $startTime;

    private int $endTime;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(int $startTime, int $endTime)
    {
        if ($startTime >= $endTime) {
            throw new InvalidArgumentException('Start time must be < end time.');
        }

        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public function isActive(int $atTime = 0): bool
    {
        $atTime = $atTime ?: \time();

        return $atTime >= $this->startTime && $atTime < $this->endTime;
    }

    public function nextTransitionAfter(int $atTime): ?int
    {
        if ($atTime < $this->startTime) {
            return $this->startTime;
        }

        if ($atTime < $this->endTime) {
            return $this->endTime;
        }

        return null;
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function getEndTime(): int
    {
        return $this->endTime;
    }

    public function toArray(): array
    {
        return [
            'type' => 'date_range',
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static((int) $data['start_time'], (int) $data['end_time']);
    }
}
