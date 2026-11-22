<?php

namespace App\Domain\Aquarium\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tank_id
 * @property ReminderType $type
 * @property string|null $label
 * @property Carbon $starts_on
 * @property int|null $interval_days
 * @property string $state
 * @property int $version
 */
#[Fillable(['tank_id', 'type', 'label', 'starts_on', 'interval_days', 'state', 'version'])]
class Reminder extends Model
{
    public const ACTIVE = 'active';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['type' => ReminderType::class, 'starts_on' => 'date'];
    }

    public function title(): string
    {
        return $this->label ?: $this->type->label();
    }

    /** @return BelongsTo<Tank, $this> */
    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    /** @return HasMany<ReminderOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(ReminderOccurrence::class);
    }
}
