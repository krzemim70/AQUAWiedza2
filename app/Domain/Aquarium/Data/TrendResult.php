<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Aquarium\Models\MeasurementMethod;

final readonly class TrendResult
{
    /**
     * @param  list<MeasurementMethod>  $methods
     * @param  list<TrendPoint>  $points
     */
    public function __construct(
        public string $parameter,
        public int $windowDays,
        public int $pointCount,
        public int $minPoints,
        public bool $enoughData,
        public ?float $firstValue,
        public ?float $lastValue,
        public ?float $delta,
        public ?float $ratePerDay,
        public bool $mixedMethods,
        public bool $hasUnspecifiedMethod,
        public array $methods,
        public ?string $statusMessage,
        public array $points,
    ) {}
}
