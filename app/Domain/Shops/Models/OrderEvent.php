<?php

namespace App\Domain\Shops\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Historia zamówienia: kto i kiedy zmienił stan.
 *
 * @property int $order_id
 * @property OrderState|null $from_state
 * @property OrderState $to_state
 * @property string|null $reason
 * @property int|null $actor_id
 * @property Carbon $occurred_at
 */
#[Table('shop_order_events')]
class OrderEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['from_state' => OrderState::class, 'to_state' => OrderState::class, 'occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
