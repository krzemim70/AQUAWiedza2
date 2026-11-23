<?php

namespace App\Domain\Aquarium\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reminder_id
 * @property Carbon $due_on
 * @property string $state
 * @property Carbon|null $completed_at
 * @property Reminder $reminder
 */
#[Fillable(['reminder_id', 'due_on', 'state', 'completed_at', 'completed_by'])]
class ReminderOccurrence extends Model
{
    public const PENDING = 'pending';

    public const DONE = 'done';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<Reminder, $this> */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    /** @return HasOne<ReminderNotice, $this> */
    public function notice(): HasOne
    {
        return $this->hasOne(ReminderNotice::class, 'occurrence_id');
    }
}
