<?php

namespace App\Domain\Shops\Data;

final readonly class ReorderResult
{
    /**
     * @param  list<string>  $added
     * @param  list<string>  $priceChanges
     * @param  list<string>  $unavailable
     */
    public function __construct(
        public int $addedCount,
        public array $added,
        public array $priceChanges,
        public array $unavailable,
    ) {}

    /** @return list<string> */
    public function issues(): array
    {
        return [...$this->priceChanges, ...$this->unavailable];
    }
}
