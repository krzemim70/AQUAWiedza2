<?php

namespace App\Domain\Shops\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Rezerwacja ilości pozycji zamówienia: held, released albo consumed.
 *
 * @property int $id
 * @property int $order_item_id
 * @property int $shop_id
 * @property int $offer_id
 * @property int $qty
 * @property string $state
 */
#[Table('shop_reservations')]
class Reservation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['held_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
