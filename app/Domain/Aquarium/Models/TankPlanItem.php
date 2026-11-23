<?php

namespace App\Domain\Aquarium\Models;

use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pozycja planu: gatunek fauny lub flory wskazany przez użytkownika.
 *
 * @property int $id
 * @property int $plan_id
 * @property Area $area
 * @property string $catalog_id
 * @property int $quantity
 */
#[Fillable(['plan_id', 'area', 'catalog_id', 'quantity'])]
class TankPlanItem extends Model
{
    protected function casts(): array
    {
        return ['area' => Area::class];
    }
}
