<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Advisor\Engine\WaterHints\Hint;
use App\Domain\Aquarium\Models\Measurement;

final readonly class WaterHintsReport
{
    /**
     * @param  list<Hint>  $hints
     * @param  array<string, WaterHintProduct>  $productsByParameter
     * @param  array<string, string>  $productBlockReasonsByParameter
     */
    public function __construct(
        public Measurement $measurement,
        public array $hints,
        public int $ageInDays,
        public bool $stale,
        public ?WaterHintProduct $product,
        public ?string $productBlockReason,
        public array $productsByParameter = [],
        public array $productBlockReasonsByParameter = [],
    ) {}
}
