<?php

namespace App\Domain\Shops\Data;

final readonly class RecordWatchCounts
{
    /** @param array<string, int> $counts */
    public function __construct(public array $counts) {}
}
