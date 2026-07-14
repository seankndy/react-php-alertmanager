<?php

namespace SeanKndy\AlertManager\Scheduling;

/**
 * Active when any child schedule is active (logical OR).
 */
final class AnyOf implements SerializableScheduleInterface, TransitionalScheduleInterface
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
            if ($schedule->isActive($atTime)) {
                return true;
            }
        }

        return false;
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
            'type' => 'any_of',
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
