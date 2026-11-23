<?php

namespace App\Domain\Aquarium\Models;

use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Obsada: klucz katalogowy (area, catalog_id) zostaje także po wycofaniu rekordu z wydania.
 *
 * @property int $id
 * @property int $tank_id
 * @property Area $area
 * @property string $catalog_id
 * @property int $quantity
 * @property list<string>|null $acknowledged_findings kody ustaleń zgodności przyjętych świadomie
 */
#[Table('tank_livestock')]
#[Fillable(['tank_id', 'area', 'catalog_id', 'quantity', 'acknowledged_findings'])]
class Livestock extends Model
{
    protected function casts(): array
    {
        return ['area' => Area::class, 'acknowledged_findings' => 'array'];
    }
}
