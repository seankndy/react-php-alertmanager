<?php

namespace SeanKndy\AlertManager\Scheduling\OnCall;

use InvalidArgumentException;

/**
 * Who is on call for one team, at any moment in time.
 *
 * A schedule is layers plus overrides, and resolution is two steps:
 *
 *   1. The highest-priority layer that has anybody on call decides the shift.
 *      Layers do not union -- a narrower layer on top masks the ones beneath it.
 *   2. Overrides are applied to the result, swapping out anyone they cover.
 *
 * Both steps are pure functions of the layers, the overrides and the clock, so a
 * schedule holds no mutable state and can be thrown away and rebuilt from
 * storage on every refresh. That is deliberate: AlertManager has no database,
 * and the consuming application owns the truth.
 *
 * One schedule maps to one on-call team ("Field Techs", "Network Techs").
 * Somebody being on call in two teams at once means two schedules, not two
 * layers.
 */
final class OnCallSchedule
{
    private string $name;

    /**
     * Layers, kept sorted highest-priority first.
     *
     * @var Layer[]
     */
    private array $layers = [];

    /**
     * @var Override[]
     */
    private array $overrides = [];

    /**
     * Ceiling on how many shifts timeline() will return, so an accidentally
     * enormous range cannot run away.
     */
    private const MAX_TIMELINE_SHIFTS = 5000;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function addLayer(Layer $layer): self
    {
        $this->layers[] = $layer;

        // usort() is not stable prior to PHP 8.0; on 8.0+ equal-priority layers
        // keep their insertion order, which is the documented tie-break.
        \usort(
            $this->layers,
            fn (Layer $a, Layer $b): int => $b->getPriority() <=> $a->getPriority()
        );

        return $this;
    }

    public function addOverride(Override $override): self
    {
        $this->overrides[] = $override;

        return $this;
    }

    /**
     * Who is on call at $atTime. 0 means now.
     *
     * @return string[]
     */
    public function participantsAt(int $atTime = 0): array
    {
        $atTime = $atTime ?: \time();

        return $this->applyOverrides($this->baseParticipantsAt($atTime), $atTime);
    }

    /**
     * Is this specific participant on call at $atTime? 0 means now.
     *
     * @param string|int $participantId
     */
    public function isOnCall($participantId, int $atTime = 0): bool
    {
        return \in_array((string) $participantId, $this->participantsAt($atTime), true);
    }

    /**
     * The current shift, with its window, as decided by the winning layer.
     *
     * The window is the winning layer's rotation turn -- it deliberately does
     * not account for a restriction cutting the turn short, because the turn is
     * what a human means by "your shift". Use timeline() when you need the exact
     * blocks during which someone is reachable.
     *
     * Null when nobody is on call at all.
     */
    public function shiftAt(int $atTime = 0): ?Shift
    {
        $atTime = $atTime ?: \time();

        foreach ($this->layers as $layer) {
            if (! $layer->appliesAt($atTime)) {
                continue;
            }

            $shift = $layer->getRotation()->shiftAt($atTime);

            if (! $shift->getParticipants()) {
                continue;
            }

            foreach ($this->overrides as $override) {
                if ($override->appliesAt($atTime)) {
                    $shift = $shift->withParticipantReplaced(
                        $override->getReplacesParticipantId(),
                        $override->getParticipantId()
                    );
                }
            }

            return $shift;
        }

        return null;
    }

    /**
     * Break [$from, $to) into contiguous blocks of "these people were on call".
     *
     * Adjacent blocks with identical participants are merged, so a rotation
     * handoff between two people who are both still on call does not show up as
     * a change. This is what a calendar view and a handoff notifier both want.
     *
     * @return Shift[]
     *
     * @throws InvalidArgumentException
     */
    public function timeline(int $from, int $to): array
    {
        if ($from >= $to) {
            throw new InvalidArgumentException('From must be < to.');
        }

        $shifts = [];
        $blockStart = $from;
        $blockParticipants = $this->participantsAt($from);
        $cursor = $from;
        $iterations = 0;

        while ($cursor < $to && $iterations++ < self::MAX_TIMELINE_SHIFTS) {
            $next = $this->nextChangeAfter($cursor, $to);

            if ($next === null || $next >= $to) {
                break;
            }

            $participants = $this->participantsAt($next);

            if ($participants !== $blockParticipants) {
                if ($blockParticipants) {
                    $shifts[] = new Shift($blockParticipants, $blockStart, $next);
                }

                $blockStart = $next;
                $blockParticipants = $participants;
            }

            $cursor = $next;
        }

        if ($blockParticipants) {
            $shifts[] = new Shift($blockParticipants, $blockStart, $to);
        }

        return $shifts;
    }

    /**
     * The next moment after $atTime at which the on-call answer actually changes,
     * or null if it does not change before $until.
     *
     * Layers and overrides each nominate the boundaries they could change at;
     * this walks those candidates in order and returns the first one where the
     * resolved answer really is different. A boundary that is masked -- a handoff
     * inside a layer that a higher layer is currently overriding, say -- is
     * skipped rather than reported as a change.
     */
    public function nextChangeAfter(int $atTime, ?int $until = null): ?int
    {
        $current = $this->participantsAt($atTime);
        $cursor = $atTime;
        $iterations = 0;

        while ($iterations++ < self::MAX_TIMELINE_SHIFTS) {
            $candidates = [];

            foreach ($this->layers as $layer) {
                foreach ($layer->transitionCandidatesAfter($cursor) as $candidate) {
                    if ($candidate > $cursor && ($until === null || $candidate < $until)) {
                        $candidates[] = $candidate;
                    }
                }
            }

            foreach ($this->overrides as $override) {
                foreach ([$override->getStartTime(), $override->getEndTime()] as $bound) {
                    if ($bound > $cursor && ($until === null || $bound < $until)) {
                        $candidates[] = $bound;
                    }
                }
            }

            if (! $candidates) {
                return null;
            }

            $next = \min($candidates);

            if ($this->participantsAt($next) !== $current) {
                return $next;
            }

            $cursor = $next;
        }

        return null;
    }

    /**
     * Everybody who could ever be put on call by this schedule: rotation members
     * plus anyone named in an override.
     *
     * A covering tech may not be in the rotation at all, so callers building
     * receivers must consult this rather than the rotations alone.
     *
     * @return string[]
     */
    public function getAllParticipants(): array
    {
        $participants = [];

        foreach ($this->layers as $layer) {
            $participants = \array_merge($participants, $layer->getRotation()->getAllParticipants());
        }

        // Only the coverer, not the person being covered: the latter is being
        // taken *off* call, and is in a rotation already if they were ever on it.
        foreach ($this->overrides as $override) {
            $participants[] = $override->getParticipantId();
        }

        return \array_values(\array_unique($participants));
    }

    /**
     * @return string[]
     */
    private function baseParticipantsAt(int $atTime): array
    {
        foreach ($this->layers as $layer) {
            $participants = $layer->participantsAt($atTime);

            if ($participants) {
                return $participants;
            }
        }

        return [];
    }

    /**
     * @param string[] $participants
     *
     * @return string[]
     */
    private function applyOverrides(array $participants, int $atTime): array
    {
        foreach ($this->overrides as $override) {
            if (! $override->appliesAt($atTime)) {
                continue;
            }

            foreach ($participants as $i => $participant) {
                if ($participant === $override->getReplacesParticipantId()) {
                    $participants[$i] = $override->getParticipantId();
                }
            }
        }

        return \array_values(\array_unique($participants));
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Layer[]
     */
    public function getLayers(): array
    {
        return $this->layers;
    }

    /**
     * @return Override[]
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'layers' => \array_map(fn (Layer $l): array => $l->toArray(), $this->layers),
            'overrides' => \array_map(fn (Override $o): array => $o->toArray(), $this->overrides),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $schedule = new self($data['name']);

        foreach ($data['layers'] ?? [] as $layer) {
            $schedule->addLayer(Layer::fromArray($layer));
        }

        foreach ($data['overrides'] ?? [] as $override) {
            $schedule->addOverride(Override::fromArray($override));
        }

        return $schedule;
    }

    public function __toString(): string
    {
        return 'on-call-schedule=' . $this->name . '; layers=' . \count($this->layers) .
            '; overrides=' . \count($this->overrides);
    }
}
