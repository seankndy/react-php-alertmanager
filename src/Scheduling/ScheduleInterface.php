<?php

namespace SeanKndy\AlertManager\Scheduling;

interface ScheduleInterface
{
    /**
     * Is this schedule active at timestamp $atTime?
     *
     * @var int $atTime A UNIX timestamp to check against schedule; 0 should be interpreted as 'now'.
     */
    public function isActive(int $atTime = 0): bool;
}
