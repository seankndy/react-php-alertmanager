<?php

namespace SeanKndy\AlertManager\Scheduling\OnCall;

use InvalidArgumentException;

/**
 * One person covering another's shift for a window of time.
 *
 * This is the thing BasicSchedule had no answer for. A tech going on vacation
 * used to mean rewriting the rotation from the current week outward, because
 * the rotation only existed as a pile of staggered repeating windows. Here the
 * rotation is untouched: an override is a single fact, "Bob covers Alice from
 * Friday to Monday", laid over the top of it. Delete it and the rotation is
 * exactly as it was.
 *
 * The window is [startTime, endTime).
 *
 * The replacement need not be in the rotation at all -- anyone can cover.
 */
final class Override
{
    /**
     * Who goes on call.
     */
    private string $participantId;

    /**
     * Whose shift they are taking. The override does nothing at moments when
     * this participant is not the one on call.
     */
    private string $replacesParticipantId;

    private int $startTime;

    private int $endTime;

    /**
     * Free-form, for the humans: "PTO", "sick", "swapped w/ Bob".
     */
    private ?string $note;

    /**
     * @param string|int $participantId
     * @param string|int $replacesParticipantId
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        $participantId,
        $replacesParticipantId,
        int $startTime,
        int $endTime,
        ?string $note = null
    ) {
        if ($startTime >= $endTime) {
            throw new InvalidArgumentException('Override start time must be < end time.');
        }

        $this->participantId = (string) $participantId;
        $this->replacesParticipantId = (string) $replacesParticipantId;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->note = $note;
    }

    public function appliesAt(int $atTime): bool
    {
        return $atTime >= $this->startTime && $atTime < $this->endTime;
    }

    public function overlaps(int $from, int $to): bool
    {
        return $this->startTime < $to && $this->endTime > $from;
    }

    public function getParticipantId(): string
    {
        return $this->participantId;
    }

    public function getReplacesParticipantId(): string
    {
        return $this->replacesParticipantId;
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function getEndTime(): int
    {
        return $this->endTime;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function toArray(): array
    {
        return [
            'participant_id' => $this->participantId,
            'replaces_participant_id' => $this->replacesParticipantId,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'note' => $this->note,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['participant_id'],
            $data['replaces_participant_id'],
            (int) $data['start_time'],
            (int) $data['end_time'],
            $data['note'] ?? null
        );
    }
}
