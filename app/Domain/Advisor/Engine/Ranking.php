<?php

namespace App\Domain\Advisor\Engine;

final readonly class Ranking
{
    /**
     * @param  list<ScoredRecord>  $matches  od najlepszego
     * @param  int  $topCount  liczba równorzędnych na szczycie (1 = jeden zwycięzca)
     * @param  ?Criterion  $decidedBy  kryterium, które rozstrzygnęło równy wynik lidera i następnego (gdy $topCount = 1)
     */
    public function __construct(
        public array $matches,
        public int $topCount = 1,
        public ?Criterion $decidedBy = null,
        public int $excluded = 0,
        public int $noData = 0,
        public ?FishBand $fish = null,
        public bool $fishDerived = false,
        public bool $fishAdjusted = false,
    ) {}

    public function top(): ?ScoredRecord
    {
        return $this->matches[0] ?? null;
    }

    /** @return list<ScoredRecord> */
    public function topGroup(): array
    {
        return array_slice($this->matches, 0, $this->topCount);
    }
}
