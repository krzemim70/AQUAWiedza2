<?php

namespace App\Domain\Aquarium\Models;

use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan nowego akwarium: wielkość, typ i wybrane pozycje. Stan `draft` albo `converted` (wiąże się z jednym zbiornikiem).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property TankType $type
 * @property TankBand $size_band
 * @property int|null $liters
 * @property TemperatureBand|null $temp_band
 * @property PhBand|null $ph_band
 * @property string $state
 * @property int|null $converted_tank_id
 */
#[Fillable(['user_id', 'name', 'type', 'size_band', 'liters', 'temp_band', 'ph_band', 'state', 'converted_tank_id'])]
class TankPlan extends Model
{
    public const DRAFT = 'draft';

    public const CONVERTED = 'converted';

    protected function casts(): array
    {
        return [
            'type' => TankType::class,
            'size_band' => TankBand::class,
            'temp_band' => TemperatureBand::class,
            'ph_band' => PhBand::class,
        ];
    }

    /** @param Builder<TankPlan> $query */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /** @return HasMany<TankPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TankPlanItem::class, 'plan_id');
    }
}
