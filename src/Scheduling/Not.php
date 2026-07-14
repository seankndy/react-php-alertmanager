<?php

namespace SeanKndy\AlertManager\Scheduling;

/**
 * Inverts a schedule.
 */
final class Not implements SerializableScheduleInterface, TransitionalScheduleInterface
{
    private ScheduleInterface $schedule;

    public function __construct(ScheduleInterface $schedule)
    {
        $this->schedule = $schedule;
    }

    public function isActive(int $atTime = 0): bool
    {
        return ! $this->schedule->isActive($atTime ?: \time());
    }

    public function nextTransitionAfter(int $atTime): ?int
    {
        // Inverting a schedule doesn't move its boundaries, only which side of
        // them is active.
        return CompositeTransitions::earliest([$this->schedule], $atTime);
    }

    public function getSchedule(): ScheduleInterface
    {
        return $this->schedule;
    }

    public function toArray(): array
    {
        return [
            'type' => 'not',
            'schedule' => ScheduleFactory::toArray($this->schedule),
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(ScheduleFactory::fromArray($data['schedule']));
    }
}
