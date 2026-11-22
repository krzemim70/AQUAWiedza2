<?php

namespace App\Domain\Shops\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jedna wysyłka koszyka (klucz ponowienia); dzieli się na zamówienia per sklep.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $idempotency_key
 */
#[Table('shop_checkouts')]
class Checkout extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'business_date' => 'date'];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
