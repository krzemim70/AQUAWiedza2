<?php

namespace App\Domain\Shops\Actions;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Data\CartBatchResult;
use App\Domain\Shops\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grupowo dodaje oferty placówki do koszyka z obsługą częściowej dostępności (bez przerywania przy niepełnym stanie).
 * Weryfikuje zakaz zakupów we własnym sklepie oraz spójność jednej placówki na sklep.
 */
class AddLocationOffersToCart
{
    public function __construct(
        private ResolveAccess $access,
        private Access $shops,
        private PublicOffers $offers,
        private Catalog $catalog,
        private Settings $settings,
    ) {}

    /**
     * @param  list<array{offerId: int, wanted: int}>  $items
     */
    public function handle(User $user, array $items): CartBatchResult
    {
        $decision = $this->access->handle($user, Feature::ShopCart);
        $decision->allowed || throw new AuthorizationException($decision->message);

        if ($items === []) {
            return new CartBatchResult(0, 0, []);
        }

        /** @var array<int, int> $requested */
        $requested = [];
        foreach ($items as $item) {
            $offerId = (int) $item['offerId'];
            $wanted = (int) $item['wanted'];
            if ($wanted < 1) {
                throw ValidationException::withMessages(['qty' => 'Ilość w pozycji musi wynosić co najmniej 1.']);
            }
            $requested[$offerId] = ($requested[$offerId] ?? 0) + $wanted;
        }

        return DB::transaction(function () use ($user, $requested) {
            User::lockForUpdate()->findOrFail($user->id);

            $offerIds = array_keys($requested);
            $visible = $this->offers->forOffers($offerIds);
            $bounds = $this->settings->get(SettingKey::ShopOrders);

            /** @var array<int, int> $locationsInRequest */
            $locationsInRequest = [];
            foreach ($visible as $offer) {
                if ($this->shops->isMember($user, $offer->shopId)) {
                    throw ValidationException::withMessages(['qty' => 'Nie możesz kupować we własnym sklepie.']);
                }
                if (isset($locationsInRequest[$offer->shopId]) && $locationsInRequest[$offer->shopId] !== $offer->locationId) {
                    throw ValidationException::withMessages(['qty' => 'Wybrane oferty z jednego sklepu muszą pochodzić z tej samej placówki.']);
                }
                $locationsInRequest[$offer->shopId] = $offer->locationId;
            }

            foreach ($locationsInRequest as $shopId => $locationId) {
                $otherLocation = DB::table('shop_cart_items')
                    ->join('shop_offers', 'shop_offers.id', '=', 'shop_cart_items.offer_id')
                    ->where('shop_cart_items.user_id', $user->id)
                    ->where('shop_cart_items.shop_id', $shopId)
                    ->where('shop_offers.location_id', '!=', $locationId)
                    ->exists();
                $otherLocation && throw ValidationException::withMessages(['qty' => 'W koszyku masz już pozycje z tego sklepu do odbioru w innej placówce. Zamów je osobno.']);
            }

            $missingIds = array_values(array_diff($offerIds, array_keys($visible)));
            /** @var array<int, Offer> $missingOffers */
            $missingOffers = $missingIds === []
                ? []
                : Offer::whereIn('id', $missingIds)->get(['id', 'area', 'legacy_id'])->keyBy('id')->all();

            /** @var array<string, list<string>> $byArea */
            $byArea = [];
            foreach ($visible as $offer) {
                $byArea[$offer->area->value][] = $offer->legacyId;
            }
            foreach ($missingOffers as $missingOffer) {
                $byArea[$missingOffer->area->value][] = $missingOffer->legacy_id;
            }
            /** @var array<string, array<string, string>> $labels */
            $labels = [];
            foreach ($byArea as $areaValue => $legacyIds) {
                $labels[$areaValue] = $this->catalog->labels(Area::from($areaValue), array_values(array_unique($legacyIds)));
            }

            $existingQty = DB::table('shop_cart_items')
                ->where('user_id', $user->id)
                ->whereIn('offer_id', $offerIds)
                ->pluck('qty', 'offer_id');

            $addedLines = 0;
            $addedUnits = 0;
            $shortfalls = [];

            foreach ($requested as $offerId => $wanted) {
                $offer = $visible[$offerId] ?? null;
                if ($offer === null) {
                    $missingOffer = $missingOffers[$offerId] ?? null;
                    $name = $missingOffer === null
                        ? "Oferta #$offerId"
                        : ($labels[$missingOffer->area->value][$missingOffer->legacy_id] ?? $missingOffer->legacy_id);
                    $shortfalls[] = [
                        'offerId' => $offerId,
                        'name' => $name,
                        'wanted' => $wanted,
                        'added' => 0,
                        'missing' => $wanted,
                        'reason' => 'Oferta nie jest już dostępna.',
                    ];

                    continue;
                }

                $name = $labels[$offer->area->value][$offer->legacyId] ?? $offer->legacyId;
                $currentInCart = (int) ($existingQty[$offerId] ?? 0);
                $canAdd = max(0, min($wanted, $offer->freeQty - $currentInCart, (int) $bounds['qty_max'] - $currentInCart));

                if ($canAdd > 0) {
                    DB::table('shop_cart_items')->updateOrInsert(
                        ['user_id' => $user->id, 'offer_id' => $offer->id],
                        [
                            'shop_id' => $offer->shopId,
                            'qty' => $currentInCart + $canAdd,
                            'price_seen_grosze' => $offer->priceGrosze,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                    $addedLines++;
                    $addedUnits += $canAdd;
                }

                if ($canAdd < $wanted) {
                    $reason = match (true) {
                        $offer->freeQty <= 0 => 'Brak dostępnych sztuk.',
                        $currentInCart >= $offer->freeQty => 'Cała dostępna ilość jest już w koszyku.',
                        $currentInCart + $wanted > (int) $bounds['qty_max'] && $offer->freeQty - $currentInCart >= $wanted => "Limit ilości w pozycji koszyka to {$bounds['qty_max']}.",
                        default => "Dostępne tylko {$offer->freeQty} szt.",
                    };
                    $shortfalls[] = [
                        'offerId' => $offerId,
                        'name' => $name,
                        'wanted' => $wanted,
                        'added' => $canAdd,
                        'missing' => $wanted - $canAdd,
                        'reason' => $reason,
                    ];
                }
            }

            return new CartBatchResult($addedLines, $addedUnits, $shortfalls);
        });
    }
}
