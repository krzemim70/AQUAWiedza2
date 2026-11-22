<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\KnowledgeBase\Models\Area;

/** Ilość 0 usuwa gatunek z obsady. */
final readonly class LivestockData
{
    public function __construct(public Area $area, public string $catalogId, public int $quantity) {}
}
