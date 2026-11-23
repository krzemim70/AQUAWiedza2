<?php

namespace App\Domain\Aquarium\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $occurrence_id
 * @property Carbon $sent_at
 */
#[Fillable(['occurrence_id', 'sent_at'])]
class ReminderNotice extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<ReminderOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ReminderOccurrence::class, 'occurrence_id');
    }
}
