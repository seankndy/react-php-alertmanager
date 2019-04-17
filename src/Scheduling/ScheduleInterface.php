<?php
namespace SeanKndy\AlertManager\Scheduling;

interface ScheduleInterface
{
    /**
     * Is this schedule active at timestamp $time?
     *
     * @var int $time UNIX timestamp to check against schedule; 0 should be
     *   interpreted as 'now'.
     *
     * @return bool
     */
    public function isActive(int $atTime = 0) : bool;
}
