<?php

namespace SeanKndy\AlertManager\Scheduling;

/**
 * A schedule that can report when its isActive() answer will next flip.
 *
 * This is what makes it possible to build a timeline (who is on call, and
 * from when until when) without polling isActive() on a fine-grained grid.
 */
interface TransitionalScheduleInterface extends ScheduleInterface
{
    /**
     * The next timestamp strictly after $atTime at which isActive() changes,
     * or null if it never changes again.
     */
    public function nextTransitionAfter(int $atTime): ?int;
}
