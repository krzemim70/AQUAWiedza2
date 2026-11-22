<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Aquarium\Models\RedfieldZone;

final readonly class RedfieldResult
{
    public function __construct(
        public float $molarRatio,
        public float $massRatio,
        public RedfieldZone $zone,
        public string $message,
    ) {}
}
