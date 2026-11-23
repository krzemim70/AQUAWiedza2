<?php

namespace App\Domain\Shops\Models;

use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Oferta sklepu na rekord katalogu (area + legacy_id). `reserved_qty` zmienia dopiero koszyk/zamówienia.
 *
 * @property int $id
 * @property int $shop_id
 * @property int $location_id
 * @property Area $area
 * @property string $legacy_id
 * @property string $variant
 * @property string $variant_key
 * @property int $price_grosze
 * @property int|null $promo_price_grosze
 * @property Carbon|null $promo_from
 * @property Carbon|null $promo_to
 * @property Carbon $valid_from
 * @property Carbon $valid_to
 * @property OfferState $state
 * @property int $free_qty
 * @property int $reserved_qty
 * @property int $version
 * @property int $terms_version
 */
#[Table('shop_offers')]
class Offer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['area' => Area::class, 'state' => OfferState::class, 'valid_from' => 'datetime', 'valid_to' => 'datetime', 'promo_from' => 'datetime', 'promo_to' => 'datetime'];
    }

    /**
     * Opublikowane i niewygasłe (także przyszłe i z zerową ilością): liczą się do limitu.
     *
     * @param  Builder<Offer>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('state', OfferState::Published->value)->where('valid_to', '>', now());
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

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }
}
