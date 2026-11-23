<?php

namespace App\Domain\Aquarium\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zdarzenie zmiany stanu zapasu (− zużycie, + uzupełnienie), z idempotencją w ramach pozycji.
 *
 * @property int $id
 * @property int $supply_id
 * @property Carbon $occurred_at
 * @property string $delta_amount
 * @property SupplyEventSource $source
 * @property string|null $device_sensor_id
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 */
#[Table('tank_supply_events')]
#[Fillable(['supply_id', 'occurred_at', 'delta_amount', 'source', 'device_sensor_id', 'idempotency_key'])]
class TankSupplyEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'delta_amount' => 'decimal:2',
            'source' => SupplyEventSource::class,
        ];
    }

    /** @return BelongsTo<TankSupply, $this> */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(TankSupply::class, 'supply_id');
    }
}
