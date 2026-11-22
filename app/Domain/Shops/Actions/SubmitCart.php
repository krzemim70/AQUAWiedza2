<?php

namespace App\Domain\Shops\Actions;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Data\CartLine;
use App\Domain\Shops\Models\Checkout;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Orders;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Wysyła cały koszyk atomowo: jedno zamówienie per sklep (nagłówek, pozycje z migawką, kontakt, historia), naliczenie
 * dziennego limitu i opróżnienie koszyka w jednej transakcji. Klucz ponowienia: ten sam klucz zwraca pierwszy wynik.
 * Bez rezerwacji; ilość może się zmienić do decyzji sklepu.
 */
class SubmitCart
{
    public function __construct(private ResolveAccess $access, private ListCart $cart, private Orders $orders, private Settings $settings) {}

    /** @return list<Order> */
    public function handle(User $user, string $idempotencyKey, string $phone): array
    {
        $decision = $this->access->handle($user, Feature::ShopCart);
        $decision->allowed || throw new AuthorizationException($decision->message);

        Validator::make(['key' => $idempotencyKey, 'phone' => $phone], [
            'key' => ['required', 'string', 'min:8', 'max:64'],
            'phone' => ['required', 'regex:/^[0-9 +()-]{7,32}$/'],
        ], ['phone.regex' => 'Podaj numer telefonu (cyfry, spacje, + i -).', 'phone.required' => 'Podaj numer telefonu.'], ['phone' => 'telefon'])->validate();

        return DB::transaction(function () use ($user, $idempotencyKey, $phone) {
            // blokada klienta serializuje wysyłki, zmiany koszyka i licznik dzienny
            $user = User::lockForUpdate()->findOrFail($user->id);

            $done = Checkout::where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($done) {
                return $done->orders()->orderBy('id')->get()->all();
            }

            $lines = $this->cart->handle($user);
            $lines !== [] || throw ValidationException::withMessages(['cart' => 'Koszyk jest pusty.']);
            $problems = array_map(fn (CartLine $l) => "{$l->name}: {$l->issue}", array_filter($lines, fn (CartLine $l) => $l->issue !== null));
            $problems === [] || throw ValidationException::withMessages(['cart' => array_values($problems)]);

            $groups = [];
            foreach ($lines as $line) {
                $groups[$line->shopId][] = $line;
            }
            foreach ($groups as $group) {
                count(array_unique(array_map(fn (CartLine $l) => $l->locationId, $group))) === 1
                    || throw ValidationException::withMessages(['cart' => "{$group[0]->shopName}: pozycje z różnych placówek odbioru. Zamów je osobno."]);
            }

            $date = now()->timezone(config('app.display_timezone'))->toDateString();
            DB::table('shop_customer_request_days')->insertOrIgnore(['user_id' => $user->id, 'business_date' => $date, 'used_orders' => 0]);
            $used = (int) DB::table('shop_customer_request_days')->where('user_id', $user->id)->where('business_date', $date)->lockForUpdate()->value('used_orders');
            $limit = (int) $this->settings->get(SettingKey::ShopOrders)['daily_orders'];
            if ($used + count($groups) > $limit) {
                throw ValidationException::withMessages(['cart' => "Dzienny limit zamówień to $limit (wykorzystano $used, w koszyku jest ".count($groups).' sklepów). Pozostało: '.max($limit - $used, 0).'.']);
            }

            $checkout = Checkout::create([
                'user_id' => $user->id, 'idempotency_key' => $idempotencyKey, 'business_date' => $date,
                'orders_count' => count($groups), 'submitted_at' => now(),
            ]);

            ksort($groups);
            $created = [];
            foreach ($groups as $shopId => $group) {
                $order = Order::create([
                    'checkout_id' => $checkout->id, 'shop_id' => $shopId, 'location_id' => $group[0]->locationId, 'user_id' => $user->id,
                    'state' => OrderState::New, 'version' => 1, 'total_grosze' => array_sum(array_map(fn (CartLine $l) => $l->total(), $group)),
                ]);
                foreach ($group as $l) {
                    $order->items()->create([
                        'shop_id' => $shopId, 'offer_id' => $l->offerId, 'area' => $l->area, 'legacy_id' => $l->legacyId, 'name' => $l->name,
                        'variant' => $l->variant, 'price_grosze' => $l->priceGrosze, 'terms_version' => $l->termsVersion, 'qty' => $l->qty,
                    ]);
                }
                $order->contact()->create(['nick' => $user->name, 'email' => $user->email, 'phone' => $phone]);
                $this->orders->event($order, null, $user);
                $this->orders->notify($order, 'submitted');
                $created[] = $order;
            }

            DB::table('shop_customer_request_days')->where('user_id', $user->id)->where('business_date', $date)->update(['used_orders' => $used + count($groups)]);
            DB::table('shop_cart_items')->where('user_id', $user->id)->delete();

            return $created;
        });
    }
}
