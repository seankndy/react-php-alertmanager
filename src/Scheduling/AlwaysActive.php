<?php

namespace SeanKndy\AlertManager\Scheduling;

/**
 * Always on. Useful as a catch-all base layer so a rotation can never leave a
 * coverage hole.
 */
final class AlwaysActive implements SerializableScheduleInterface, TransitionalScheduleInterface
{
    public function isActive(int $atTime = 0): bool
    {
        return true;
    }

    public function nextTransitionAfter(int $atTime): ?int
    {
        return null;
    }

    public function toArray(): array
    {
        return ['type' => 'always'];
    }

    public static function fromArray(array $data): static
    {
        return new static();
    }
}
