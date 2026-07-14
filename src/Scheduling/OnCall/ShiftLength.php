<?php

namespace SeanKndy\AlertManager\Scheduling\OnCall;

use InvalidArgumentException;

/**
 * How long one turn in a rotation lasts.
 *
 * Lengths are held in minutes but remember the unit they were declared in, so
 * a weekly rotation round-trips through storage as "1 week" rather than
 * "10080 minutes".
 *
 * The unit is only cosmetic: Rotation lays shifts out on the wall clock, so a
 * 1-week and a 7-day shift behave identically, and both survive DST.
 */
final class ShiftLength
{
    public const UNIT_WEEKS = 'weeks';
    public const UNIT_DAYS = 'days';
    public const UNIT_HOURS = 'hours';
    public const UNIT_MINUTES = 'minutes';

    private const MINUTES_PER_UNIT = [
        self::UNIT_WEEKS => 7 * 24 * 60,
        self::UNIT_DAYS => 24 * 60,
        self::UNIT_HOURS => 60,
        self::UNIT_MINUTES => 1,
    ];

    private string $unit;

    private int $count;

    /**
     * @throws InvalidArgumentException
     */
    private function __construct(string $unit, int $count)
    {
        if (! isset(self::MINUTES_PER_UNIT[$unit])) {
            throw new InvalidArgumentException(
                "Invalid shift unit '$unit', must be one of [" .
                    \implode(', ', \array_keys(self::MINUTES_PER_UNIT)) . '].'
            );
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Shift length must be at least 1.');
        }

        $this->unit = $unit;
        $this->count = $count;
    }

    public static function weeks(int $count = 1): self
    {
        return new self(self::UNIT_WEEKS, $count);
    }

    public static function days(int $count = 1): self
    {
        return new self(self::UNIT_DAYS, $count);
    }

    public static function hours(int $count): self
    {
        return new self(self::UNIT_HOURS, $count);
    }

    public static function minutes(int $count): self
    {
        return new self(self::UNIT_MINUTES, $count);
    }

    public function inMinutes(): int
    {
        return self::MINUTES_PER_UNIT[$this->unit] * $this->count;
    }

    public function inSeconds(): int
    {
        return $this->inMinutes() * 60;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function toArray(): array
    {
        return ['unit' => $this->unit, 'count' => $this->count];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self($data['unit'], (int) $data['count']);
    }

    public function __toString(): string
    {
        return $this->count . ' ' . ($this->count === 1
            ? \rtrim($this->unit, 's')
            : $this->unit);
    }
}
