<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;

class AddToLivestock
{
    public function __construct(private ChangeLivestock $change) {}

    /** Dodaje 1 sztukę gatunku do obsady zbiornika: zwiększa istniejącą ilość albo zakłada wpis z ilością 1. */
    public function handle(User $actor, int $tankId, Area $area, string $catalogId): void
    {
        $current = (int) Livestock::where(['tank_id' => $tankId, 'area' => $area->value, 'catalog_id' => $catalogId])->value('quantity');

        $this->change->handle($actor, $tankId, new LivestockData($area, $catalogId, $current + 1));
    }
}
