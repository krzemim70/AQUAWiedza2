<?php

namespace App\Domain\Aquarium\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property TankType $type
 * @property TankSize $size_band
 * @property FishSize|null $fish_size_band
 * @property TempBand|null $temp_band
 * @property PhBand|null $ph_band
 * @property string|null $notes
 */
#[Fillable(['user_id', 'name', 'type', 'size_band', 'fish_size_band', 'temp_band', 'ph_band', 'notes'])]
class Tank extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TankType::class,
            'size_band' => TankSize::class,
            'fish_size_band' => FishSize::class,
            'temp_band' => TempBand::class,
            'ph_band' => PhBand::class,
        ];
    }

    /** @param Builder<Tank> $query */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    public function volumeLiters(): int
    {
        return $this->size_band->volume();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Livestock, $this> */
    public function livestock(): HasMany
    {
        return $this->hasMany(Livestock::class);
    }

    /** @return HasMany<Measurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    /**
     * Ostatni pomiar: po czasie, przy remisie po ID.
     *
     * @return HasOne<Measurement, $this>
     */
    public function latestMeasurement(): HasOne
    {
        return $this->hasOne(Measurement::class)->ofMany(['measured_at' => 'max', 'id' => 'max']);
    }

    /** @return HasMany<JournalEntry, $this> */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
