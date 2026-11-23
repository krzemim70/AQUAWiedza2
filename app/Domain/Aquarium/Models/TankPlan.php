<?php

namespace App\Domain\Aquarium\Models;

use App\Domain\Advisor\Engine\TankBand;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan nowego akwarium: wielkość, typ i wybrane gatunki. Stan `draft` albo `converted` (wiąże się z jednym zbiornikiem).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property TankType $type
 * @property TankBand $size_band
 * @property int|null $liters
 * @property string $state
 * @property int|null $converted_tank_id
 */
#[Fillable(['user_id', 'name', 'type', 'size_band', 'liters', 'state', 'converted_tank_id'])]
class TankPlan extends Model
{
    public const DRAFT = 'draft';

    public const CONVERTED = 'converted';

    protected function casts(): array
    {
        return ['type' => TankType::class, 'size_band' => TankBand::class];
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
