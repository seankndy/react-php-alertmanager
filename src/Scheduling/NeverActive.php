<?php

namespace SeanKndy\AlertManager\Scheduling;

/**
 * Never on. Useful for disabling a layer without deleting it.
 */
final class NeverActive implements SerializableScheduleInterface, TransitionalScheduleInterface
{
    public function isActive(int $atTime = 0): bool
    {
        return false;
    }

    public function nextTransitionAfter(int $atTime): ?int
    {
        return null;
    }

    public function toArray(): array
    {
        return ['type' => 'never'];
    }

    public static function fromArray(array $data): static
    {
        return new static();
    }
}
