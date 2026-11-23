<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Aquarium\Models\MeasurementMethod;
use DateTimeInterface;

final readonly class TrendPoint
{
    public function __construct(
        public DateTimeInterface $measuredAt,
        public float $value,
        public ?MeasurementMethod $method = null,
    ) {}
}
