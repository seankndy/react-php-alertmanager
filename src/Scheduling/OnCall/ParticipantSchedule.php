<?php

namespace SeanKndy\AlertManager\Scheduling\OnCall;

use SeanKndy\AlertManager\Scheduling\ScheduleInterface;

/**
 * Binds one participant to one on-call schedule so a Receiver can use it.
 *
 * An OnCallSchedule answers "who is on call?", while a Receiver needs to know
 * "am *I* on call?". This is the adapter between the two, and it is the only
 * thing the rest of AlertManager needs to know about the on-call model:
 *
 *     $receiver->addSchedule(new ParticipantSchedule($fieldTechs, $userId));
 *
 * AbstractReceiver ORs a receiver's schedules together, so a tech who is in two
 * rotations just gets two of these.
 */
final class ParticipantSchedule implements ScheduleInterface
{
    private OnCallSchedule $schedule;

    private string $participantId;

    /**
     * @param string|int $participantId
     */
    public function __construct(OnCallSchedule $schedule, $participantId)
    {
        $this->schedule = $schedule;
        $this->participantId = (string) $participantId;
    }

    public function isActive(int $atTime = 0): bool
    {
        return $this->schedule->isOnCall($this->participantId, $atTime);
    }

    public function getSchedule(): OnCallSchedule
    {
        return $this->schedule;
    }

    public function getParticipantId(): string
    {
        return $this->participantId;
    }

    public function __toString(): string
    {
        return 'participant=' . $this->participantId . '; schedule=' . $this->schedule->getName();
    }
}
