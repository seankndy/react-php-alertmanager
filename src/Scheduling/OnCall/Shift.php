<?php

namespace SeanKndy\AlertManager\Scheduling\OnCall;

/**
 * One resolved turn in a rotation: who is up, and for what window.
 *
 * This is what you render on a calendar, or diff to notice that a handoff just
 * happened.
 */
final class Shift
{
    /**
     * @var string[]
     */
    private array $participants;

    private int $startTime;

    private int $endTime;

    /**
     * The rotation's turn number. Zero is the turn beginning at the rotation's
     * anchor; turns before the anchor are negative.
     */
    private int $index;

    /**
     * @param string[] $participants
     */
    public function __construct(array $participants, int $startTime, int $endTime, int $index = 0)
    {
        $this->participants = \array_values($participants);
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->index = $index;
    }

    /**
     * @return string[]
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function getEndTime(): int
    {
        return $this->endTime;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function contains(int $atTime): bool
    {
        return $atTime >= $this->startTime && $atTime < $this->endTime;
    }

    /**
     * Same participants, replacing $replaced with $replacement wherever present.
     */
    public function withParticipantReplaced(string $replaced, string $replacement): self
    {
        $participants = $this->participants;

        foreach ($participants as $i => $participant) {
            if ($participant === $replaced) {
                $participants[$i] = $replacement;
            }
        }

        return new self(\array_values(\array_unique($participants)), $this->startTime, $this->endTime, $this->index);
    }

    public function toArray(): array
    {
        return [
            'participants' => $this->participants,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'index' => $this->index,
        ];
    }
}
