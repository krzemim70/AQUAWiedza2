<?php

namespace App\Domain\Shops\Contracts;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Data\PublicOffer;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\ShopState;

/**
 * Odczyt widocznych ofert dla innych modułów (doradca, koszyk). Jedna polityka kwalifikacji: oferta opublikowana,
 * w oknie ważności `[od, do)`, sklep aktywny, placówka czynna, rekord katalogu aktywny. Ilość 0 jest zwracana (niedostępna).
 */
class PublicOffers
{
    public function __construct(private Catalog $catalog) {}

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
        $offers = Offer::query()->with(['shop', 'location'])
            ->where('area', $area->value)->whereIn('legacy_id', array_keys($present))
            ->where('state', OfferState::Published->value)->where('valid_from', '<=', now())->where('valid_to', '>', now())
            ->whereHas('shop', fn ($q) => $q->where('state', ShopState::Active->value))
            ->whereHas('location', fn ($q) => $q->where('active', true))
            ->orderBy('price_grosze')->orderBy('id')->get();

        $result = [];
        foreach ($offers as $o) {
            $result[$o->legacy_id][] = new PublicOffer(
                $o->id, $o->area, $o->legacy_id, $o->variant, $o->price_grosze, $o->free_qty, $o->valid_from, $o->valid_to,
                $o->shop_id, $o->shop->name, $o->location_id, $o->location->name, $o->location->address, $o->location->city,
            );
        }

        return $result;
    }
}
