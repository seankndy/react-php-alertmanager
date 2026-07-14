<?php

namespace SeanKndy\AlertManager\Scheduling\OnCall;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A round-robin rotation: an ordered list of turns, each held by one or more
 * participants, repeating forever.
 *
 * The rotation is defined by three things and nothing else:
 *
 *   - the turn order,
 *   - how long a turn lasts,
 *   - the moment the first turn began (the anchor).
 *
 * Everything else is derived. Who is up at time T is a modulus, not a search,
 * so evaluating a rotation costs the same whether T is tomorrow or in 2040.
 * Adding, removing, or reordering participants is a change to the turn order
 * alone; no timestamps move, and no future turns need to be rewritten.
 *
 *     // three techs, week about, first turn started Mon Jan 5 2026 08:00
 *     new Rotation(['alice', 'bob', 'carl'], ShiftLength::weeks(1), $anchor, 'America/Denver');
 *
 * A turn may be held by more than one participant, which is how you put two
 * people on call together:
 *
 *     new Rotation([['alice', 'bob'], ['carl', 'dave']], ...);
 *
 * Turn boundaries are laid out on the wall clock rather than by adding seconds,
 * so a rotation that hands off at 08:00 keeps handing off at 08:00 across a DST
 * transition instead of drifting to 07:00 or 09:00.
 */
final class Rotation
{
    /**
     * Ordered turns. Each turn is a list of participant ids.
     *
     * @var array<int, string[]>
     */
    private array $turns;

    private ShiftLength $shiftLength;

    /**
     * The instant the first turn (index 0) begins.
     */
    private int $anchorTime;

    private DateTimeZone $timezone;

    /**
     * Ceiling on the boundary-correction loop in shiftIndexAt(). The estimate is
     * only ever off by a DST offset, so this is a guard against a pathological
     * timezone, not a working limit.
     */
    private const MAX_CORRECTION_STEPS = 8;

    /**
     * @param array<int, string|int|array<string|int>> $turns Ordered turns; each entry is one participant or a list of them
     * @param DateTimeZone|string $timezone
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $turns, ShiftLength $shiftLength, int $anchorTime, $timezone)
    {
        $normalized = [];

        foreach (\array_values($turns) as $turn) {
            $participants = \is_array($turn) ? $turn : [$turn];
            $participants = \array_values(\array_unique(\array_map('strval', $participants)));

            if (! $participants) {
                throw new InvalidArgumentException('A rotation turn must have at least one participant.');
            }

            $normalized[] = $participants;
        }

        if (! $normalized) {
            throw new InvalidArgumentException('A rotation must have at least one turn.');
        }

        $this->turns = $normalized;
        $this->shiftLength = $shiftLength;
        $this->anchorTime = $anchorTime;
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
    }

    /**
     * A rotation of one turn: the given participants are always up.
     *
     * Useful as a static assignment in a layer -- a permanent daytime team, or
     * a catch-all fallback beneath the real rotation.
     *
     * @param array<string|int> $participants
     * @param DateTimeZone|string $timezone
     */
    public static function fixed(array $participants, $timezone): self
    {
        return new self([$participants], ShiftLength::weeks(1), 0, $timezone);
    }

    /**
     * Who is up at $atTime. 0 means now.
     *
     * @return string[]
     */
    public function participantsAt(int $atTime = 0): array
    {
        $atTime = $atTime ?: \time();
        $count = \count($this->turns);
        $index = $this->shiftIndexAt($atTime);

        // PHP's % keeps the sign of the dividend, so negative turn indices
        // (times before the anchor) need to be brought back into range.
        return $this->turns[(($index % $count) + $count) % $count];
    }

    /**
     * The full shift in progress at $atTime. 0 means now.
     */
    public function shiftAt(int $atTime = 0): Shift
    {
        $atTime = $atTime ?: \time();
        $index = $this->shiftIndexAt($atTime);

        return $this->shiftAtIndex($index);
    }

    /**
     * Every shift overlapping [$from, $to). This is what a calendar view wants.
     *
     * @return Shift[]
     *
     * @throws InvalidArgumentException
     */
    public function shiftsBetween(int $from, int $to): array
    {
        if ($from >= $to) {
            throw new InvalidArgumentException('From must be < to.');
        }

        $shifts = [];

        for ($index = $this->shiftIndexAt($from);; $index++) {
            $shift = $this->shiftAtIndex($index);

            if ($shift->getStartTime() >= $to) {
                break;
            }

            $shifts[] = $shift;
        }

        return $shifts;
    }

    /**
     * The next handoff strictly after $atTime.
     */
    public function nextHandoffAfter(int $atTime): int
    {
        return $this->shiftStartTime($this->shiftIndexAt($atTime) + 1);
    }

    private function shiftAtIndex(int $index): Shift
    {
        $count = \count($this->turns);

        return new Shift(
            $this->turns[(($index % $count) + $count) % $count],
            $this->shiftStartTime($index),
            $this->shiftStartTime($index + 1),
            $index
        );
    }

    /**
     * Which turn number is in progress at $atTime.
     *
     * Dividing elapsed time by the shift length gives the answer directly in a
     * world without DST. With DST the wall-clock layout means a shift can be an
     * hour longer or shorter in real time than the divisor assumes, so the
     * estimate is nudged onto the true boundary afterwards. That correction is
     * at most a step or two -- it never walks the rotation.
     */
    public function shiftIndexAt(int $atTime): int
    {
        $index = (int) \floor(($atTime - $this->anchorTime) / $this->shiftLength->inSeconds());

        $steps = 0;

        while ($this->shiftStartTime($index) > $atTime && $steps++ < self::MAX_CORRECTION_STEPS) {
            $index--;
        }

        while ($this->shiftStartTime($index + 1) <= $atTime && $steps++ < self::MAX_CORRECTION_STEPS) {
            $index++;
        }

        return $index;
    }

    /**
     * When turn $index begins, as an absolute timestamp.
     *
     * The shift offset is applied to the anchor's local date and time-of-day
     * rather than to its timestamp. Whole days are added as calendar days and
     * the remainder as a wall-clock time, so a handoff pinned to 08:00 stays at
     * 08:00 through a DST transition. Fixed-second arithmetic would move it.
     */
    public function shiftStartTime(int $index): int
    {
        $anchor = (new DateTimeImmutable('@' . $this->anchorTime))->setTimezone($this->timezone);

        $anchorMinuteOfDay = ((int) $anchor->format('G')) * 60 + (int) $anchor->format('i');
        $offsetMinutes = $anchorMinuteOfDay + $index * $this->shiftLength->inMinutes();

        // intdiv() truncates toward zero; shifts before the anchor need a floor.
        $dayOffset = \intdiv($offsetMinutes, 1440);
        $minuteOfDay = $offsetMinutes % 1440;

        if ($minuteOfDay < 0) {
            $minuteOfDay += 1440;
            $dayOffset--;
        }

        // Noon is the day cursor because midnight does not exist on DST-transition
        // days in some timezones, and modify() would silently roll it forward.
        return $anchor
            ->setTime(12, 0, 0)
            ->modify(\sprintf('%+d days', $dayOffset))
            ->setTime(\intdiv($minuteOfDay, 60), $minuteOfDay % 60, 0)
            ->getTimestamp();
    }

    /**
     * Every participant in the rotation, flattened and de-duplicated.
     *
     * @return string[]
     */
    public function getAllParticipants(): array
    {
        return \array_values(\array_unique(\array_merge(...$this->turns)));
    }

    /**
     * @return array<int, string[]>
     */
    public function getTurns(): array
    {
        return $this->turns;
    }

    public function getShiftLength(): ShiftLength
    {
        return $this->shiftLength;
    }

    public function getAnchorTime(): int
    {
        return $this->anchorTime;
    }

    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function toArray(): array
    {
        return [
            'turns' => $this->turns,
            'shift_length' => $this->shiftLength->toArray(),
            'anchor_time' => $this->anchorTime,
            'timezone' => $this->timezone->getName(),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['turns'],
            ShiftLength::fromArray($data['shift_length']),
            (int) $data['anchor_time'],
            $data['timezone']
        );
    }
}
