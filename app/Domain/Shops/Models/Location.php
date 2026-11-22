<?php

namespace App\Domain\Shops\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Placówka odbioru.
 *
 * @property int $id
 * @property int $shop_id
 * @property string $name
 * @property string $address
 * @property string $city
 * @property bool $active
 */
#[Table('shop_locations')]
#[Fillable(['name', 'address', 'city', 'phone', 'opening_hours', 'active'])]
class Location extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
