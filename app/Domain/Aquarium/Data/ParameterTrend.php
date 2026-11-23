<?php

namespace App\Domain\Aquarium\Data;

use Carbon\CarbonInterface;

final readonly class ParameterTrend
{
    /**
     * @param  list<array{min: ?float, max: ?float, label: string}>  $stockRanges
     * @param  list<array{measured_at: CarbonInterface, value: string, method_label: ?string, warnings: list<string>}>  $rows
     */
    public function __construct(
        public string $parameter,
        public string $label,
        public TrendResult $trend,
        public array $stockRanges,
        public bool $hasStockRangeSupport,
        public array $rows,
    ) {}
}
