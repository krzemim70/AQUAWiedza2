<?php

namespace App\Domain\Shops;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderEvent;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Reservation;
use App\Domain\Shops\Models\StockMovement;
use App\Domain\Shops\Notifications\OrderNotice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Wspólne klocki operacji na zamówieniu: blokada nagłówka, zmiana stanu z historią, rozliczenie rezerwacji
 * i powiadomienia po zatwierdzeniu transakcji. Kolejność blokad: zamówienie, oferty rosnąco po id, rezerwacje, licznik EAN.
 */
class Orders
{
    public function __construct(private Settings $settings) {}

    /** Blokuje nagłówek zamówienia (wywołać w transakcji). */
    public function lock(Order $order): Order
    {
        return Order::lockForUpdate()->findOrFail($order->id);
    }

    /** Zmienia stan, zapisuje historię (kto, kiedy, dlaczego); stan końcowy ustala termin pseudonimizacji. */
    public function move(Order $order, OrderState $to, ?User $actor, ?string $reason = null): void
    {
        $from = $order->state;
        $order->state = $to;
        $order->version++;
        if ($to->isFinal()) {
            $order->finished_at = now();
            $order->pseudonymize_at = now()->addDays((int) $this->settings->get(SettingKey::ShopOrders)['pseudonymize_days']);
        }
        $order->save();

        $this->event($order, $from, $actor, $reason);
    }

    public function event(Order $order, ?OrderState $from, ?User $actor, ?string $reason = null): void
    {
        OrderEvent::create([
            'shop_id' => $order->shop_id, 'order_id' => $order->id, 'order_version' => $order->version, 'from_state' => $from,
            'to_state' => $order->state, 'reason' => $reason === null ? null : mb_substr($reason, 0, 255),
            'actor_id' => $actor?->id, 'occurred_at' => now(),
        ]);
    }

    /**
     * Zamyka aktywne rezerwacje zamówienia: `released` zwraca sztuki do dostępnych, `consumed` je wydaje.
     * Rezerwacja jest rozliczana tylko w stanie `held`, więc powtórka niczego nie zwraca drugi raz.
     */
    public function resolveReservations(Order $order, string $to): void
    {
        $held = Reservation::whereIn('order_item_id', $order->items()->pluck('id'))->where('state', 'held')->orderBy('offer_id')->lockForUpdate()->get();
        $offers = Offer::whereIn('id', $held->pluck('offer_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($held as $reservation) {
            $offer = $offers[$reservation->offer_id];
            $release = $to === 'released';
            $offer->forceFill([
                'free_qty' => $offer->free_qty + ($release ? $reservation->qty : 0),
                'reserved_qty' => $offer->reserved_qty - $reservation->qty,
                'version' => $offer->version + 1,
            ])->save();
            StockMovement::create([
                'shop_id' => $offer->shop_id, 'offer_id' => $offer->id, 'kind' => $release ? 'release' : 'issue',
                'delta_free' => $release ? $reservation->qty : 0, 'delta_reserved' => -$reservation->qty,
                'free_after' => $offer->free_qty, 'reserved_after' => $offer->reserved_qty, 'reason' => "Zamówienie #{$order->id}", 'occurred_at' => now(),
            ]);
            $reservation->forceFill(['state' => $to, 'resolved_at' => now()])->save();
        }
    }

    /** Wiadomość wychodzi dopiero po zatwierdzeniu transakcji (przy rollbacku nie wychodzi wcale). */
    public function notify(Order $order, string $kind): void
    {
        DB::afterCommit(function () use ($order, $kind) {
            $order->refresh();
            $recipients = $kind === 'submitted'
                ? User::whereIn('id', Member::where('shop_id', $order->shop_id)->pluck('user_id'))->get()
                : ($order->contactVisible() ? collect([$order->customer])->filter() : collect());
            Notification::send($recipients, new OrderNotice($order, $kind));
        });
    }
}
