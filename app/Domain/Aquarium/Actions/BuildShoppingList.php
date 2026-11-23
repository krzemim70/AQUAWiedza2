<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\ShoppingList;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\OfferAvailability;
use App\Models\User;

/**
 * Pozycje planu zestawione z widocznymi ofertami z miasta użytkownika (bez miasta: wszystkie oferty), po placówce.
 * Oferta bez dostępnej ilości jest pomijana; pozycja bez żadnej oferty trafia do „brak ofert w okolicy”.
 */
class BuildShoppingList
{
    public function __construct(private Access $access, private PublicOffers $offers, private Catalog $catalog) {}

    public function handle(User $actor, int $planId): ShoppingList
    {
        $plan = $this->access->plan($actor, $planId);
        $this->access->authorize($actor, Feature::PublicOffers);
        $cityKey = OfferAvailability::cityKey($actor->city);
        $items = $plan->items()->orderBy('id')->get();

        $groups = [];
        $missing = [];
        foreach ([Area::Fauna, Area::Flora] as $area) {
            $rows = $items->where('area', $area);
            $ids = $rows->pluck('catalog_id')->all();
            $names = $this->catalog->labels($area, $ids);
            $offers = $this->offers->forRecords($area, $ids);
            foreach ($rows as $row) {
                $name = $names[$row->catalog_id] ?? $row->catalog_id;
                $found = array_filter($offers[$row->catalog_id] ?? [], fn ($o) => $o->freeQty > 0 && ($cityKey === null || OfferAvailability::cityKey($o->locationCity) === $cityKey));
                if ($found === []) {
                    $missing[] = ['area' => $area, 'id' => $row->catalog_id, 'name' => $name, 'wanted' => $row->quantity];

                    continue;
                }
                foreach ($found as $o) {
                    $groups[$o->locationId] ??= ['shop' => $o->shopName, 'location' => $o->locationName, 'address' => $o->locationAddress, 'city' => $o->locationCity, 'lines' => []];
                    $groups[$o->locationId]['lines'][] = [
                        'area' => $area, 'id' => $row->catalog_id, 'name' => $name, 'wanted' => $row->quantity,
                        'offerId' => $o->id, 'variant' => $o->variant, 'freeQty' => $o->freeQty, 'priceGrosze' => $o->priceGrosze,
                    ];
                }
            }
        }
        uasort($groups, fn ($a, $b) => [count($b['lines']), $a['shop'], $a['location']] <=> [count($a['lines']), $b['shop'], $b['location']]);

        return new ShoppingList(array_values($groups), $missing, $cityKey === null ? null : trim((string) $actor->city));
    }
}
