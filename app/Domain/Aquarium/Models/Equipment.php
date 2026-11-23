<?php

namespace App\Domain\Aquarium\Models;

use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Wyposażenie zbiornika: rekord katalogu sprzętu (area, catalog_id) wskazany przez użytkownika.
 *
 * @property int $id
 * @property int $tank_id
 * @property Area $area
 * @property string $catalog_id
 * @property int $quantity
 * @property Carbon|null $installed_on
 */
#[Table('tank_equipment')]
#[Fillable(['tank_id', 'area', 'catalog_id', 'quantity', 'installed_on'])]
class Equipment extends Model
{
    protected function casts(): array
    {
        return ['area' => Area::class, 'installed_on' => 'date'];
    }
}
