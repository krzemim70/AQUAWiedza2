<?php

namespace App\Domain\Shops\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Zamówienie w jednym sklepie (jedna placówka odbioru). EAN-13 dostaje przy decyzji sklepu.
 *
 * @property int $id
 * @property int $checkout_id
 * @property int $shop_id
 * @property int $location_id
 * @property int|null $user_id
 * @property OrderState $state
 * @property int $version
 * @property int $total_grosze
 * @property string|null $ean13
 * @property string|null $rejection_code
 * @property string|null $note
 * @property Carbon|null $decided_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $pseudonymize_at
 * @property Carbon|null $pseudonymized_at
 * @property Carbon $created_at
 */
#[Table('shop_orders')]
class Order extends Model
{
    public const REJECTION_CODES = [
        'brak_aktualnej_oferty' => 'Brak aktualnej oferty',
        'inne' => 'Inne (opis obowiązkowy)',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state' => OrderState::class, 'decided_at' => 'datetime', 'finished_at' => 'datetime',
            'pseudonymize_at' => 'datetime', 'pseudonymized_at' => 'datetime',
        ];
    }

    /** Kontakt znika po terminie także wtedy, gdy zadanie pseudonimizacji jeszcze się nie wykonało. */
    public function contactVisible(): bool
    {
        return $this->pseudonymized_at === null && ($this->pseudonymize_at === null || $this->pseudonymize_at->isFuture());
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /** @return HasMany<OrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    /** @return HasOne<OrderContact, $this> */
    public function contact(): HasOne
    {
        return $this->hasOne(OrderContact::class);
    }
}
