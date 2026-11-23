<?php

namespace App\Domain\Shops\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\StockMovement;
use App\Domain\Shops\PriceHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tworzy szkic oferty albo zmienia jej warunki (cena, wariant, ważność). Produkt i placówka są stałe;
 * zmiana ilości to osobna operacja (SetAvailableQuantity), tu ilość tylko otwiera historię.
 */
class SaveOffer
{
    public function __construct(private Access $access, private Settings $settings, private Catalog $catalog, private PriceHistory $history) {}

    public function handle(User $actor, Shop $shop, OfferData $data, ?Offer $offer = null): Offer
    {
        $this->access->authorizeMember($actor, $shop->id);

        $bounds = $this->settings->get(SettingKey::ShopOfferBounds);
        $minPrice = (int) $this->settings->get(SettingKey::ShopMinPrice);
        Validator::make([
            'price' => $data->priceGrosze, 'variant' => $data->variant, 'valid_from' => $data->validFrom, 'valid_to' => $data->validTo, 'qty' => $data->freeQty,
        ], [
            'price' => ['required', 'integer', 'min:'.max($minPrice, 1), 'max:'.$bounds['max_price_grosze']],
            'variant' => ['nullable', 'string', 'max:120'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after:valid_from'],
            'qty' => ['integer', 'min:0', 'max:'.$bounds['max_qty']],
        ], [], ['price' => 'cena', 'valid_to' => 'ważna do', 'valid_from' => 'ważna od', 'qty' => 'dostępna ilość'])->validate();

        return DB::transaction(function () use ($actor, $shop, $data, $offer) {
            Shop::lockForUpdate()->findOrFail($shop->id);
            $variantKey = Str::lower(Str::squish($data->variant));

            if ($offer === null) {
                if (! Location::where('shop_id', $shop->id)->whereKey($data->locationId)->where('active', true)->exists()) {
                    throw ValidationException::withMessages(['location_id' => 'Wybierz czynną placówkę swojego sklepu.']);
                }
                if ($this->catalog->labels($data->area, [$data->legacyId]) === []) {
                    throw ValidationException::withMessages(['legacy_id' => 'Wybierz pozycję z katalogu.']);
                }
                $offer = new Offer(['shop_id' => $shop->id, 'location_id' => $data->locationId, 'area' => $data->area, 'legacy_id' => $data->legacyId]);
            } else {
                $offer = Offer::where('shop_id', $shop->id)->lockForUpdate()->findOrFail($offer->id);
                $changed = $offer->variant_key !== $variantKey || $offer->price_grosze !== $data->priceGrosze
                    || ! $offer->valid_from->equalTo($data->validFrom) || ! $offer->valid_to->equalTo($data->validTo);
                if ($offer->promo_price_grosze !== null && $offer->promo_to > now() && $data->priceGrosze <= $offer->promo_price_grosze) {
                    throw ValidationException::withMessages(['price' => 'Cena regularna musi być wyższa od ceny promocyjnej. Najpierw zakończ promocję.']);
                }
                $offer->version++;
                $offer->terms_version += $changed ? 1 : 0;
            }

            $taken = Offer::where('shop_id', $shop->id)->where('location_id', $offer->location_id)->where('area', $offer->area->value)
                ->where('legacy_id', $offer->legacy_id)->where('variant_key', $variantKey)->where('id', '!=', $offer->id ?? 0)->exists();
            if ($taken) {
                throw ValidationException::withMessages(['variant' => 'Ta pozycja z takim wariantem jest już w ofercie tej placówki.']);
            }

            $new = ! $offer->exists;
            $priceChanged = ! $new && $offer->price_grosze !== $data->priceGrosze;
            $offer->forceFill([
                'variant' => trim($data->variant), 'variant_key' => $variantKey, 'price_grosze' => $data->priceGrosze,
                'valid_from' => $data->validFrom, 'valid_to' => $data->validTo,
            ] + ($new ? ['free_qty' => $data->freeQty] : []))->save();

            if ($priceChanged && $offer->state === OfferState::Published) {
                $this->history->changeRegular($offer);
            }

            if ($new) {
                StockMovement::create([
                    'shop_id' => $shop->id, 'offer_id' => $offer->id, 'kind' => 'opening', 'delta_free' => $data->freeQty,
                    'free_after' => $data->freeQty, 'reserved_after' => 0, 'actor_id' => $actor->id, 'occurred_at' => now(),
                ]);
            }

            return $offer->refresh();
        });
    }
}
