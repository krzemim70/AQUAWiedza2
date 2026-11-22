<?php

namespace App\Domain\Shops\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Szkic, wygasłą lub wycofaną ofertę publikuje sklep po ponownej walidacji; limit liczony pod blokadą sklepu. */
class PublishOffer
{
    public function __construct(private Access $access, private Settings $settings, private Catalog $catalog, private EvaluateShop $evaluate) {}

    public function handle(User $actor, Offer $offer): Offer
    {
        $this->access->authorizeMember($actor, $offer->shop_id);

        return DB::transaction(function () use ($offer) {
            $shop = Shop::lockForUpdate()->findOrFail($offer->shop_id);
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);

            $fail = fn (string $message) => throw ValidationException::withMessages(['offer' => $message]);

            $offer->state === OfferState::Published && $fail('Oferta jest już opublikowana.');
            in_array($shop->state, [ShopState::Preparation, ShopState::Active], true) || $fail('Sklep nie może teraz publikować ofert.');
            $offer->valid_to->isFuture() || $fail('Termin ważności oferty już minął. Zmień datę „ważna do”.');
            Location::where('shop_id', $shop->id)->whereKey($offer->location_id)->where('active', true)->exists() || $fail('Placówka tej oferty jest nieaktywna.');
            $this->catalog->labels($offer->area, [$offer->legacy_id]) !== [] || $fail('Pozycja nie jest już dostępna w katalogu.');

            $limit = (int) $this->settings->get(SettingKey::ShopActivation)['offer_limit'];
            Offer::where('shop_id', $shop->id)->live()->count() < $limit || $fail("Osiągnięto limit $limit opublikowanych ofert.");

            $offer->forceFill(['state' => OfferState::Published, 'version' => $offer->version + 1])->save();
            $this->evaluate->handle($shop);

            return $offer;
        });
    }
}
