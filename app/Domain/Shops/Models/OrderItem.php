<?php

namespace App\Domain\Shops\Models;

use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Pozycja zamówienia z migawką nazwy, wariantu i ceny z chwili wysyłki.
 *
 * @property int $id
 * @property int $shop_id
 * @property int $order_id
 * @property int $offer_id
 * @property Area $area
 * @property string $legacy_id
 * @property string $name
 * @property string $variant
 * @property int $price_grosze
 * @property int $terms_version
 * @property int $qty
 */
#[Table('shop_order_items')]
class OrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['area' => Area::class];
    }
}
