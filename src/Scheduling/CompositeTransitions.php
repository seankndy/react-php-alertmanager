<?php

namespace SeanKndy\AlertManager\Scheduling;

/**
 * Shared transition logic for composite schedules.
 *
 * @internal
 */
final class CompositeTransitions
{
    /**
     * The earliest transition among $schedules after $atTime.
     *
     * A composite can only change when one of its children changes, so the
     * union of the children's boundaries is a superset of the composite's own.
     * (It is a superset and not an exact set: an OR of two overlapping windows
     * has boundaries that are not real transitions. Callers that need exactness
     * must re-evaluate isActive() at each boundary, which OnCallSchedule does.)
     *
     * Children that cannot report transitions are skipped, so a schedule mixing
     * transitional and non-transitional children yields an under-approximation.
     *
     * @param ScheduleInterface[] $schedules
     */
    public static function earliest(array $schedules, int $atTime): ?int
    {
        $earliest = null;

        foreach ($schedules as $schedule) {
            if (! $schedule instanceof TransitionalScheduleInterface) {
                continue;
            }

            $transition = $schedule->nextTransitionAfter($atTime);

            if ($transition === null) {
                continue;
            }

            if ($earliest === null || $transition < $earliest) {
                $earliest = $transition;
            }
        }

        return $earliest;
    }
}
