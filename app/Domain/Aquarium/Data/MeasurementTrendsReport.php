<?php

namespace App\Domain\Aquarium\Data;

final readonly class MeasurementTrendsReport
{
    /**
     * @param  list<int>  $availableWindows
     * @param  list<ParameterTrend>  $trends
     */
    public function __construct(
        public int $windowDays,
        public array $availableWindows,
        public int $minPoints,
        public array $trends,
    ) {}
}
