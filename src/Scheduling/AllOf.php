<?php

namespace SeanKndy\AlertManager\Scheduling;

/**
 * Active only when every child schedule is active (logical AND).
 *
 * Combined with Not, this is how you express "on-call nights and weekends,
 * except on company holidays":
 *
 *     new AllOf([
 *         $nightsAndWeekends,
 *         new Not(new AnyOf($holidays)),
 *     ]);
 *
 * An empty set of children is active, which keeps AllOf usable as a default.
 */
final class AllOf implements SerializableScheduleInterface, TransitionalScheduleInterface
{
    /**
     * @var ScheduleInterface[]
     */
    private array $schedules;

    /**
     * @param ScheduleInterface[] $schedules
     */
    public function __construct(array $schedules)
    {
        $this->schedules = \array_values($schedules);
    }

    public function isActive(int $atTime = 0): bool
    {
        $atTime = $atTime ?: \time();

        foreach ($this->schedules as $schedule) {
            if (! $schedule->isActive($atTime)) {
                return false;
            }
        }

        return true;
    }

    public function nextTransitionAfter(int $atTime): ?int
    {
        return CompositeTransitions::earliest($this->schedules, $atTime);
    }

    /**
     * @return ScheduleInterface[]
     */
    public function getSchedules(): array
    {
        return $this->schedules;
    }

    public function toArray(): array
    {
        return [
            'type' => 'all_of',
            'schedules' => \array_map(
                fn (ScheduleInterface $schedule): array => ScheduleFactory::toArray($schedule),
                $this->schedules
            ),
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(\array_map(
            fn (array $child): ScheduleInterface => ScheduleFactory::fromArray($child),
            $data['schedules']
        ));
    }
}
