<?php

namespace App\Domain\Aquarium\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Zapas preparatu lub karmy przypisany do zbiornika (klucz katalogu z obszaru pielęgnacji).
 *
 * @property int $id
 * @property int $tank_id
 * @property string $catalog_id
 * @property SupplyUnit $unit
 * @property string $package_amount
 * @property string $remaining_amount
 * @property Carbon $balance_at
 * @property string $dose_amount
 * @property string|null $dose_per_liters
 * @property int|null $interval_days
 * @property int|null $preferred_shop_id
 * @property Carbon|null $low_stock_notified_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('tank_supplies')]
#[Fillable([
    'tank_id', 'catalog_id', 'unit', 'package_amount', 'remaining_amount', 'balance_at',
    'dose_amount', 'dose_per_liters', 'interval_days', 'preferred_shop_id', 'low_stock_notified_at', 'archived_at',
])]
class TankSupply extends Model
{
    protected function casts(): array
    {
        return [
            'unit' => SupplyUnit::class,
            'package_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'balance_at' => 'datetime',
            'dose_amount' => 'decimal:2',
            'dose_per_liters' => 'decimal:2',
            'interval_days' => 'integer',
            'preferred_shop_id' => 'integer',
            'low_stock_notified_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @param Builder<TankSupply> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** @return BelongsTo<Tank, $this> */
    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    /** @return HasMany<TankSupplyEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TankSupplyEvent::class, 'supply_id')->orderBy('occurred_at')->orderBy('id');
    }
}
