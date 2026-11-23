<?php

namespace App\Domain\Shops\Contracts;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Data\PublicOffer;
use App\Domain\Shops\Data\ShopRating;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\ShopState;
use Illuminate\Database\Eloquent\Builder;

/**
 * Odczyt widocznych ofert dla innych modułów (doradca, koszyk). Jedna polityka kwalifikacji: oferta opublikowana,
 * w oknie ważności `[od, do)`, sklep aktywny, placówka czynna, rekord katalogu aktywny. Ilość 0 jest zwracana (niedostępna).
 */
class PublicOffers
{
    public function __construct(private Catalog $catalog, private ShopRatings $ratings) {}

    /**
     * @param  list<string>  $legacyIds
     * @return array<string, list<PublicOffer>> po `legacy_id`; rekordy bez ofert są pominięte; oferty od najtańszej
     */
    public function forRecords(Area $area, array $legacyIds): array
    {
        if ($legacyIds === []) {
            return [];
        }

        $present = $this->catalog->labels($area, $legacyIds);
        $offers = $this->visible()->where('area', $area->value)->whereIn('legacy_id', array_keys($present))->get();
        $ratings = $this->ratings->forShops($offers->pluck('shop_id')->unique()->values()->all());

        $result = [];
        foreach ($offers as $o) {
            $result[$o->legacy_id][] = $this->dto($o, $ratings[$o->shop_id]);
        }

        return $result;
    }

    /**
     * Widoczne oferty po identyfikatorach (koszyk): ta sama polityka kwalifikacji co w `forRecords`.
     *
     * @param  list<int>  $ids
     * @return array<int, PublicOffer> po id oferty; niewidoczne pominięte
     */
    public function forOffers(array $ids): array
    {
        $offers = $this->visible()->whereIn('id', $ids)->get();
        $present = [];
        foreach ($offers->groupBy(fn (Offer $o) => $o->area->value) as $area => $group) {
            $present[$area] = $this->catalog->labels(Area::from($area), $group->pluck('legacy_id')->all());
        }

        $offers = $offers->filter(fn (Offer $o) => isset($present[$o->area->value][$o->legacy_id]));
        $ratings = $this->ratings->forShops($offers->pluck('shop_id')->unique()->values()->all());

        return $offers->mapWithKeys(fn (Offer $o) => [$o->id => $this->dto($o, $ratings[$o->shop_id])])->all();
    }

    /** @return Builder<Offer> */
    private function visible(): Builder
    {
        return Offer::query()->with(['shop', 'location'])
            ->where('state', OfferState::Published->value)->where('valid_from', '<=', now())->where('valid_to', '>', now())
            ->whereHas('shop', fn ($q) => $q->where('state', ShopState::Active->value))
            ->whereHas('location', fn ($q) => $q->where('active', true))
            ->orderBy('price_grosze')->orderBy('id');
    }

    private function dto(Offer $o, ShopRating $rating): PublicOffer
    {
        return new PublicOffer(
            $o->id, $o->area, $o->legacy_id, $o->variant, $o->price_grosze, $o->free_qty, $o->valid_from, $o->valid_to,
            $o->shop_id, $o->shop->name, $o->location_id, $o->location->name, $o->location->address, $o->location->city,
            $o->terms_version,
            shopRatingAvg: $rating->average,
            shopRatingCount: $rating->count,
        );
    }
}
