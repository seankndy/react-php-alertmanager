<?php

namespace SeanKndy\AlertManager\Scheduling\OnCall;

use SeanKndy\AlertManager\Scheduling\ScheduleFactory;
use SeanKndy\AlertManager\Scheduling\ScheduleInterface;
use SeanKndy\AlertManager\Scheduling\TransitionalScheduleInterface;
use InvalidArgumentException;

/**
 * A rotation plus the conditions under which it applies.
 *
 * Layers stack. Within a schedule the highest-priority layer that has anybody
 * on call wins outright -- it is not a union. That gives you a base layer that
 * always has someone, with narrower layers on top of it:
 *
 *     priority 0   fallback         static: the on-call manager, 24/7
 *     priority 10  business hours   rotation, restricted to Mon-Fri 08:00-17:00
 *     priority 20  after hours      rotation, restricted to nights and weekends
 *
 * A restriction is any ScheduleInterface, so "nights and weekends, but not on a
 * company holiday" composes out of the primitives in the parent namespace.
 *
 * effectiveFrom/effectiveUntil bound the layer absolutely, which is how you
 * retire a rotation on a date or stand a new one up ahead of time without
 * touching the one it replaces.
 */
final class Layer
{
    private string $name;

    private Rotation $rotation;

    /**
     * When this layer is eligible at all. Null means always.
     */
    private ?ScheduleInterface $restriction;

    /**
     * Higher wins. Ties are broken by insertion order into the schedule.
     */
    private int $priority;

    private ?int $effectiveFrom;

    private ?int $effectiveUntil;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $name,
        Rotation $rotation,
        ?ScheduleInterface $restriction = null,
        int $priority = 0,
        ?int $effectiveFrom = null,
        ?int $effectiveUntil = null
    ) {
        if ($effectiveFrom !== null && $effectiveUntil !== null && $effectiveFrom >= $effectiveUntil) {
            throw new InvalidArgumentException('Effective-from must be < effective-until.');
        }

        $this->name = $name;
        $this->rotation = $rotation;
        $this->restriction = $restriction;
        $this->priority = $priority;
        $this->effectiveFrom = $effectiveFrom;
        $this->effectiveUntil = $effectiveUntil;
    }

    /**
     * Who this layer puts on call at $atTime, or [] if it does not apply then.
     *
     * @return string[]
     */
    public function participantsAt(int $atTime = 0): array
    {
        $atTime = $atTime ?: \time();

        if (! $this->appliesAt($atTime)) {
            return [];
        }

        return $this->rotation->participantsAt($atTime);
    }

    public function appliesAt(int $atTime): bool
    {
        if ($this->effectiveFrom !== null && $atTime < $this->effectiveFrom) {
            return false;
        }

        if ($this->effectiveUntil !== null && $atTime >= $this->effectiveUntil) {
            return false;
        }

        return $this->restriction === null || $this->restriction->isActive($atTime);
    }

    /**
     * Timestamps after $atTime at which this layer's answer could change: a
     * rotation handoff, a restriction boundary, or the layer's own bounds.
     *
     * These are candidates, not confirmed changes -- OnCallSchedule re-evaluates
     * at each one, because a boundary in one layer may be masked by another.
     *
     * @return int[]
     */
    public function transitionCandidatesAfter(int $atTime): array
    {
        $candidates = [$this->rotation->nextHandoffAfter($atTime)];

        if ($this->restriction instanceof TransitionalScheduleInterface) {
            $transition = $this->restriction->nextTransitionAfter($atTime);

            if ($transition !== null) {
                $candidates[] = $transition;
            }
        }

        foreach ([$this->effectiveFrom, $this->effectiveUntil] as $bound) {
            if ($bound !== null && $bound > $atTime) {
                $candidates[] = $bound;
            }
        }

        return $candidates;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRotation(): Rotation
    {
        return $this->rotation;
    }

    public function getRestriction(): ?ScheduleInterface
    {
        return $this->restriction;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getEffectiveFrom(): ?int
    {
        return $this->effectiveFrom;
    }

    public function getEffectiveUntil(): ?int
    {
        return $this->effectiveUntil;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'rotation' => $this->rotation->toArray(),
            'restriction' => $this->restriction !== null
                ? ScheduleFactory::toArray($this->restriction)
                : null,
            'priority' => $this->priority,
            'effective_from' => $this->effectiveFrom,
            'effective_until' => $this->effectiveUntil,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            Rotation::fromArray($data['rotation']),
            isset($data['restriction']) && $data['restriction'] !== null
                ? ScheduleFactory::fromArray($data['restriction'])
                : null,
            (int) ($data['priority'] ?? 0),
            isset($data['effective_from']) ? (int) $data['effective_from'] : null,
            isset($data['effective_until']) ? (int) $data['effective_until'] : null
        );
    }
}
