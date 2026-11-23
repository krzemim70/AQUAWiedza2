<?php

namespace App\Domain\Shops\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shop_id
 * @property int|null $order_id
 * @property int|null $customer_id
 * @property int $rating
 * @property string|null $comment
 * @property string|null $hidden_reason
 * @property Carbon $created_at
 */
#[Table('shop_reviews')]
class Review extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @param Builder<Review> $query */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNull('hidden_reason');
    }
}
