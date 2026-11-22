<?php

namespace App\Domain\Advisor\Engine;

final readonly class ScoredRecord
{
    /** @param array<string, int> $scores oceny 0–3 po wartości {@see Criterion}, tylko kryteria ocenione dla tego rekordu */
    public function __construct(
        public FaunaRecord $record,
        public int $points,
        public int $maxPoints,
        public array $scores,
    ) {}

    public function percent(): int
    {
        return (int) round(100 * $this->points / $this->maxPoints);
    }
}
