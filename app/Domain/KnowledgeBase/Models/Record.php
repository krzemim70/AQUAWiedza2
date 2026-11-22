<?php

namespace App\Domain\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Stały klucz publiczny rekordu: (area, legacy_id). Pozostaje po wycofaniu rekordu z wydania.
 *
 * @property int $id
 * @property Area $area
 * @property string $legacy_id
 */
#[Table('catalog_records')]
#[Fillable(['area', 'legacy_id'])]
class Record extends Model
{
    protected function casts(): array
    {
        return ['area' => Area::class];
    }

    /** @return HasOne<Description, $this> */
    public function description(): HasOne
    {
        return $this->hasOne(Description::class);
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}
