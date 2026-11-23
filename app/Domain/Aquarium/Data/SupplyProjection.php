<?php

namespace App\Domain\Aquarium\Data;

final readonly class SupplyProjection
{
    public function __construct(
        public float $remainingAmount,
        public ?float $dosePerUse,
        public ?int $daysRemaining,
        public ?string $missingDataReason,
        public bool $hasSchedule,
    ) {}
}
