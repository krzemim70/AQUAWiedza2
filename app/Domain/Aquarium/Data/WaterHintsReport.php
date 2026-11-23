<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Advisor\Engine\WaterHints\Hint;
use App\Domain\Aquarium\Models\Measurement;

final readonly class WaterHintsReport
{
    /** @param list<Hint> $hints */
    public function __construct(
        public Measurement $measurement,
        public array $hints,
        public int $ageInDays,
        public bool $stale,
    ) {}
}
