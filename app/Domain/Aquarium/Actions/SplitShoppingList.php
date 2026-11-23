<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\CreatorLine;
use App\Domain\Aquarium\Data\ShoppingLocationGroup;
use App\Domain\Aquarium\Data\ShoppingOfferLine;
use App\Domain\Aquarium\Data\ShoppingSplit;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Data\PublicOffer;
use App\Domain\Shops\OfferAvailability;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Dzieli listę pozycji (ze wszystkich 4 obszarów katalogu) po widocznych ofertach placówek w mieście,
 * także bez zapisanego planu w bazie.
 */
class SplitShoppingList
{
    public function __construct(
        private Access $access,
        private PublicOffers $offers,
        private Catalog $catalog,
    ) {}

    /**
     * @param  list<CreatorLine>  $lines
     */
    public function forLines(?User $actor, array $lines, ?string $city, bool $guestRequiresCity = false): ShoppingSplit
    {
        $this->access->authorize($actor, Feature::CreatorOffers);

        $cityKey = OfferAvailability::cityKey($city);
        if ($actor === null && $guestRequiresCity && $cityKey === null) {
            throw ValidationException::withMessages(['city' => 'Wybierz miasto, aby zobaczyć oferty sklepów w okolicy.']);
        }

        /** @var array<string, array<string, int>> $wantedByArea */
        $wantedByArea = [];
        foreach ($lines as $line) {
            if ($line->quantity < 1) {
                continue;
            }
            $wantedByArea[$line->area->value][$line->catalogId] = ($wantedByArea[$line->area->value][$line->catalogId] ?? 0) + $line->quantity;
        }

        /** @var array<int, array{locationId: int, shopId: int, shop: string, location: string, address: string, city: string, items: array<string, non-empty-list<ShoppingOfferLine>>, lines: list<ShoppingOfferLine>}> $perLocation */
        $perLocation = [];
        $missing = [];

        foreach (Area::cases() as $area) {
            $wantedMap = $wantedByArea[$area->value] ?? [];
            if ($wantedMap === []) {
                continue;
            }
            $ids = array_keys($wantedMap);
            $names = $this->catalog->labels($area, $ids);
            $offersByRecord = $this->offers->forRecords($area, $ids);

            foreach ($wantedMap as $id => $wanted) {
                $name = $names[$id] ?? $id;
                $found = array_values(array_filter(
                    $offersByRecord[$id] ?? [],
                    fn (PublicOffer $o) => $o->freeQty > 0 && ($cityKey === null || OfferAvailability::cityKey($o->locationCity) === $cityKey),
                ));
                if ($found === []) {
                    $missing[] = ['area' => $area, 'id' => $id, 'name' => $name, 'wanted' => $wanted];

                    continue;
                }
                $itemKey = $area->value.':'.$id;
                foreach ($found as $offer) {
                    $perLocation[$offer->locationId] ??= [
                        'locationId' => $offer->locationId,
                        'shopId' => $offer->shopId,
                        'shop' => $offer->shopName,
                        'location' => $offer->locationName,
                        'address' => $offer->locationAddress,
                        'city' => $offer->locationCity,
                        'items' => [],
                        'lines' => [],
                    ];
                    $offerLine = new ShoppingOfferLine(
                        $area,
                        $id,
                        $name,
                        $wanted,
                        $offer->id,
                        $offer->variant,
                        $offer->freeQty,
                        min($wanted, $offer->freeQty),
                        $offer->priceGrosze,
                        $offer,
                    );
                    $perLocation[$offer->locationId]['items'][$itemKey][] = $offerLine;
                    $perLocation[$offer->locationId]['lines'][] = $offerLine;
                }
            }
        }

        $groups = [];
        foreach ($perLocation as $loc) {
            $coveredCount = count($loc['items']);
            $fullyCoveredCount = 0;
            $totalGrosze = 0;

            foreach ($loc['items'] as $itemLines) {
                $cheapest = $itemLines[0];
                $maxFree = $cheapest->freeQty;
                foreach ($itemLines as $itemLine) {
                    if ($itemLine->freeQty > $maxFree) {
                        $maxFree = $itemLine->freeQty;
                    }
                    if ([$itemLine->priceGrosze, $itemLine->offerId] < [$cheapest->priceGrosze, $cheapest->offerId]) {
                        $cheapest = $itemLine;
                    }
                }
                if ($maxFree >= $cheapest->wanted) {
                    $fullyCoveredCount++;
                }
                $totalGrosze += $cheapest->priceGrosze * $cheapest->takeQty;
            }

            $groups[] = new ShoppingLocationGroup(
                $loc['locationId'],
                $loc['shopId'],
                $loc['shop'],
                $loc['location'],
                $loc['address'],
                $loc['city'],
                $coveredCount,
                $fullyCoveredCount,
                $totalGrosze,
                $loc['lines'],
            );
        }

        usort(
            $groups,
            fn (ShoppingLocationGroup $a, ShoppingLocationGroup $b) => [$b->coveredCount, $b->fullyCoveredCount, $a->totalGrosze, $a->shop, $a->location]
                <=> [$a->coveredCount, $a->fullyCoveredCount, $b->totalGrosze, $b->shop, $b->location],
        );

        return new ShoppingSplit($groups, $missing, $cityKey === null ? null : trim((string) $city));
    }
}
