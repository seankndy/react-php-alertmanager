<?php

namespace SeanKndy\AlertManager\Scheduling;

/**
 * A schedule that can round-trip through a plain array.
 *
 * AlertManager holds no state of its own, so consumers persist schedules in
 * their own storage and rebuild them on each refresh. Implementing this lets
 * ScheduleFactory reconstruct a schedule without the consumer knowing the
 * concrete class.
 */
interface SerializableScheduleInterface extends ScheduleInterface
{
    /**
     * Must include a 'type' key that ScheduleFactory can dispatch on.
     */
    public function toArray(): array;

    public static function fromArray(array $data): static;
}
