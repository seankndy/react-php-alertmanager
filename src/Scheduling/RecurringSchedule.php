<?php

namespace SeanKndy\AlertManager\Scheduling;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A weekly-recurring window expressed in wall-clock terms: "these days of the
 * week, from this time of day until this time of day".
 *
 * Unlike BasicSchedule, nothing here is anchored to an absolute timestamp, so
 * the recurrence never drifts and evaluating it costs the same whether you ask
 * about today or a decade from now.
 *
 * The window may wrap past midnight. If $endTime <= $startTime the window runs
 * into the following day, which is how you express an overnight on-call
 * restriction:
 *
 *     // weeknights, 17:00 until 08:00 the next morning
 *     new RecurringSchedule([1,2,3,4,5], '17:00', '08:00', 'America/Denver');
 *
 * $startTime == $endTime means the whole day.
 *
 * Days of week are the days a window *starts* on, ISO-8601 numbered
 * (1 = Monday ... 7 = Sunday).
 */
final class RecurringSchedule implements SerializableScheduleInterface, TransitionalScheduleInterface
{
    /**
     * ISO-8601 day numbers (1=Mon..7=Sun) on which a window begins.
     *
     * @var int[]
     */
    private array $daysOfWeek;

    /**
     * Minutes past local midnight at which the window opens.
     */
    private int $startMinute;

    /**
     * Minutes past local midnight at which the window closes. If <= startMinute
     * the window closes on the following day.
     */
    private int $endMinute;

    private DateTimeZone $timezone;

    /**
     * Absolute bounds outside of which this recurrence does not apply at all.
     */
    private ?int $effectiveFrom;

    private ?int $effectiveUntil;

    /**
     * @param int[] $daysOfWeek ISO-8601 day numbers, 1 (Mon) through 7 (Sun)
     * @param string $startTime 'HH:MM' local wall-clock
     * @param string $endTime 'HH:MM' local wall-clock; <= $startTime wraps past midnight
     * @param DateTimeZone|string $timezone
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        array $daysOfWeek,
        string $startTime,
        string $endTime,
        $timezone,
        ?int $effectiveFrom = null,
        ?int $effectiveUntil = null
    ) {
        if (! $daysOfWeek) {
            throw new InvalidArgumentException('At least one day of week is required.');
        }

        foreach ($daysOfWeek as $day) {
            if (! \is_int($day) || $day < 1 || $day > 7) {
                throw new InvalidArgumentException(
                    'Days of week must be ISO-8601 integers 1 (Monday) through 7 (Sunday).'
                );
            }
        }

        if ($effectiveFrom !== null && $effectiveUntil !== null && $effectiveFrom >= $effectiveUntil) {
            throw new InvalidArgumentException('Effective-from must be < effective-until.');
        }

        $this->daysOfWeek = \array_values(\array_unique($daysOfWeek));
        \sort($this->daysOfWeek);

        $this->startMinute = self::parseTimeOfDay($startTime);
        $this->endMinute = self::parseTimeOfDay($endTime);
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        $this->effectiveFrom = $effectiveFrom;
        $this->effectiveUntil = $effectiveUntil;
    }

    /**
     * Every day of the week, all day.
     *
     * @param DateTimeZone|string $timezone
     */
    public static function everyDay($timezone): self
    {
        return new self([1, 2, 3, 4, 5, 6, 7], '00:00', '00:00', $timezone);
    }

    /**
     * Monday through Friday between the given wall-clock times.
     *
     * @param DateTimeZone|string $timezone
     */
    public static function weekdays(string $startTime, string $endTime, $timezone): self
    {
        return new self([1, 2, 3, 4, 5], $startTime, $endTime, $timezone);
    }

    /**
     * Saturday and Sunday, all day.
     *
     * @param DateTimeZone|string $timezone
     */
    public static function weekends($timezone): self
    {
        return new self([6, 7], '00:00', '00:00', $timezone);
    }

    public function isActive(int $atTime = 0): bool
    {
        $atTime = $atTime ?: \time();

        if ($this->effectiveFrom !== null && $atTime < $this->effectiveFrom) {
            return false;
        }

        if ($this->effectiveUntil !== null && $atTime >= $this->effectiveUntil) {
            return false;
        }

        // A window that began yesterday may still be open (it wrapped past
        // midnight), so both candidate start days have to be considered.
        foreach ([0, -1] as $dayOffset) {
            [$start, $end] = $this->windowStartingOnDayOf($atTime, $dayOffset);

            if ($start === null) {
                continue;
            }

            if ($atTime >= $start && $atTime < $end) {
                return true;
            }
        }

        return false;
    }

    public function nextTransitionAfter(int $atTime): ?int
    {
        $candidates = [];

        if ($this->effectiveFrom !== null && $this->effectiveFrom > $atTime) {
            $candidates[] = $this->effectiveFrom;
        }

        if ($this->effectiveUntil !== null && $this->effectiveUntil > $atTime) {
            $candidates[] = $this->effectiveUntil;
        }

        // Any boundary within the next 8 days covers a full week of recurrence
        // plus the possible wrap from the day before.
        for ($dayOffset = -1; $dayOffset <= 8; $dayOffset++) {
            [$start, $end] = $this->windowStartingOnDayOf($atTime, $dayOffset);

            if ($start === null) {
                continue;
            }

            if ($start > $atTime) {
                $candidates[] = $start;
            }

            if ($end > $atTime) {
                $candidates[] = $end;
            }
        }

        $candidates = \array_filter($candidates, function (int $candidate) use ($atTime): bool {
            if ($this->effectiveFrom !== null && $candidate < $this->effectiveFrom) {
                return false;
            }

            if ($this->effectiveUntil !== null && $candidate > $this->effectiveUntil) {
                return false;
            }

            return $candidate > $atTime;
        });

        return $candidates ? \min($candidates) : null;
    }

    /**
     * Resolve the window that starts on the local day $dayOffset days away from
     * $atTime, or [null, null] if that day is not one this schedule recurs on.
     *
     * Windows are built by setting the wall-clock time on a local date rather
     * than by adding seconds, so a DST jump moves the window with the clock
     * instead of shifting it by an hour.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function windowStartingOnDayOf(int $atTime, int $dayOffset): array
    {
        // Noon is used as the day cursor because midnight does not exist on
        // DST-transition days in some timezones.
        $day = (new DateTimeImmutable('@' . $atTime))
            ->setTimezone($this->timezone)
            ->setTime(12, 0, 0)
            ->modify(\sprintf('%+d days', $dayOffset));

        if (! \in_array((int) $day->format('N'), $this->daysOfWeek, true)) {
            return [null, null];
        }

        $start = $day->setTime(\intdiv($this->startMinute, 60), $this->startMinute % 60, 0);
        $end = $day->setTime(\intdiv($this->endMinute, 60), $this->endMinute % 60, 0);

        if ($this->endMinute <= $this->startMinute) {
            $end = $end->modify('+1 day');
        }

        return [$start->getTimestamp(), $end->getTimestamp()];
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function parseTimeOfDay(string $time): int
    {
        if (! \preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches)) {
            throw new InvalidArgumentException("Time of day must be 'HH:MM' (24-hour), got '$time'.");
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }

    private static function formatTimeOfDay(int $minute): string
    {
        return \sprintf('%02d:%02d', \intdiv($minute, 60), $minute % 60);
    }

    public function toArray(): array
    {
        return [
            'type' => 'recurring',
            'days_of_week' => $this->daysOfWeek,
            'start_time' => self::formatTimeOfDay($this->startMinute),
            'end_time' => self::formatTimeOfDay($this->endMinute),
            'timezone' => $this->timezone->getName(),
            'effective_from' => $this->effectiveFrom,
            'effective_until' => $this->effectiveUntil,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            \array_map('intval', $data['days_of_week']),
            $data['start_time'],
            $data['end_time'],
            $data['timezone'],
            isset($data['effective_from']) ? (int) $data['effective_from'] : null,
            isset($data['effective_until']) ? (int) $data['effective_until'] : null
        );
    }
}
