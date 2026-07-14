<?php

namespace SeanKndy\AlertManager\Scheduling;

use InvalidArgumentException;

/**
 * Rebuilds schedules from plain arrays.
 *
 * AlertManager has no storage of its own; the consuming application persists
 * schedules however it likes (a JSON column, normalized tables, a flat file)
 * and hands the decoded array back here to reconstruct the object graph.
 */
final class ScheduleFactory
{
    /**
     * @var array<string, class-string<SerializableScheduleInterface>>
     */
    private static array $types = [
        'always' => AlwaysActive::class,
        'never' => NeverActive::class,
        'date_range' => DateRangeSchedule::class,
        'recurring' => RecurringSchedule::class,
        'any_of' => AnyOf::class,
        'all_of' => AllOf::class,
        'not' => Not::class,
    ];

    /**
     * Register a schedule type of your own so it can round-trip alongside the
     * built-in ones.
     *
     * @param class-string<SerializableScheduleInterface> $class
     *
     * @throws InvalidArgumentException
     */
    public static function register(string $type, string $class): void
    {
        if (! \is_a($class, SerializableScheduleInterface::class, true)) {
            throw new InvalidArgumentException(
                "$class must implement " . SerializableScheduleInterface::class . '.'
            );
        }

        self::$types[$type] = $class;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): ScheduleInterface
    {
        if (! isset($data['type'])) {
            throw new InvalidArgumentException("Schedule array is missing a 'type' key.");
        }

        if (! isset(self::$types[$data['type']])) {
            throw new InvalidArgumentException(
                "Unknown schedule type '{$data['type']}'. Known types: " .
                    \implode(', ', \array_keys(self::$types)) . '.'
            );
        }

        return (self::$types[$data['type']])::fromArray($data);
    }

    /**
     * Decode a JSON string into a schedule. Returns null for null/empty input
     * so callers can pass a nullable database column straight through.
     *
     * @throws InvalidArgumentException
     */
    public static function fromJson(?string $json): ?ScheduleInterface
    {
        if ($json === null || \trim($json) === '') {
            return null;
        }

        $data = \json_decode($json, true);

        if (! \is_array($data)) {
            throw new InvalidArgumentException('Schedule JSON did not decode to an array.');
        }

        return self::fromArray($data);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function toArray(ScheduleInterface $schedule): array
    {
        if (! $schedule instanceof SerializableScheduleInterface) {
            throw new InvalidArgumentException(
                \get_class($schedule) . ' does not implement ' .
                    SerializableScheduleInterface::class . ' and cannot be serialized.'
            );
        }

        return $schedule->toArray();
    }
}
