<?php

namespace App\Domain\Shops\Actions;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dodaje ofertę do koszyka (`$add`: zwiększa ilość) albo ustawia ilość; 0 usuwa pozycję. Bez rezerwacji.
 * Ilość nie przekracza dostępnej ani granic z ustawień; nie kupuje się we własnym sklepie; jedna placówka na sklep.
 */
class ChangeCart
{
    public function __construct(private ResolveAccess $access, private Access $shops, private PublicOffers $offers, private Settings $settings) {}

    public function handle(User $user, int $offerId, int $qty, bool $add = false): void
    {
        $decision = $this->access->handle($user, Feature::ShopCart);
        $decision->allowed || throw new AuthorizationException($decision->message);

        if (! $add && $qty === 0) {
            DB::table('shop_cart_items')->where('user_id', $user->id)->where('offer_id', $offerId)->delete();

            return;
        }

        $offer = $this->offers->forOffers([$offerId])[$offerId] ?? throw ValidationException::withMessages(['qty' => 'Ta oferta nie jest już dostępna.']);
        $this->shops->isMember($user, $offer->shopId) && throw ValidationException::withMessages(['qty' => 'Nie możesz kupować we własnym sklepie.']);
        $bounds = $this->settings->get(SettingKey::ShopOrders);

        DB::transaction(function () use ($user, $offer, $qty, $add, $bounds) {
            User::lockForUpdate()->findOrFail($user->id);
            $current = (int) DB::table('shop_cart_items')->where('user_id', $user->id)->where('offer_id', $offer->id)->value('qty');
            $new = $add ? $current + $qty : $qty;

            $new >= $bounds['qty_min'] && $new <= $bounds['qty_max'] || throw ValidationException::withMessages(['qty' => "Ilość w pozycji musi mieścić się w zakresie {$bounds['qty_min']}–{$bounds['qty_max']}."]);
            $new <= $offer->freeQty || throw ValidationException::withMessages(['qty' => "Dostępna ilość to {$offer->freeQty}."]);

            $otherLocation = DB::table('shop_cart_items')->join('shop_offers', 'shop_offers.id', '=', 'shop_cart_items.offer_id')
                ->where('shop_cart_items.user_id', $user->id)->where('shop_cart_items.shop_id', $offer->shopId)
                ->where('shop_offers.location_id', '!=', $offer->locationId)->exists();
            $otherLocation && throw ValidationException::withMessages(['qty' => 'W koszyku masz już pozycje z tego sklepu do odbioru w innej placówce. Zamów je osobno.']);

            DB::table('shop_cart_items')->updateOrInsert(
                ['user_id' => $user->id, 'offer_id' => $offer->id],
                ['shop_id' => $offer->shopId, 'qty' => $new, 'price_seen_grosze' => $offer->priceGrosze, 'created_at' => now(), 'updated_at' => now()],
            );
        });
    }
}
