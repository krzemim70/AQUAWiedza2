<?php

namespace App\Domain\IdentityAccess\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property AccessChangeKind $kind
 * @property Carbon|null $fishroom_ends_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'actor_id', 'kind', 'fishroom_ends_at', 'reason', 'created_at'])]
class AccessChange extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['kind' => AccessChangeKind::class, 'fishroom_ends_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
