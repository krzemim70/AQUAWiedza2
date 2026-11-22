<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\EanNumber;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Reservation;
use App\Domain\Shops\Models\StockMovement;
use App\Domain\Shops\Orders;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sklep zatwierdza całe zamówienie: sprawdza bieżące warunki i ilości wszystkich pozycji, rezerwuje je razem
 * (dostępna → zarezerwowana) i nadaje numer EAN-13, w jednej transakcji. Cokolwiek zawiedzie, nic się nie zmienia.
 * Powtórzenie zatwierdzonego zamówienia nie ma skutku.
 */
class ApproveOrder
{
    public function __construct(private Access $access, private Orders $orders, private EanNumber $ean) {}

    public function handle(User $actor, Order $order): Order
    {
        return DB::transaction(function () use ($actor, $order) {
            $order = $this->orders->lock($order);
            $this->access->authorizeMember($actor, $order->shop_id);

            if ($order->state === OrderState::Confirmed) {
                return $order;
            }
            $order->state === OrderState::New || throw ValidationException::withMessages(['order' => 'Zamówienie nie jest już nowe, więc nie można go zatwierdzić.']);

            $items = $order->items()->get();
            $offers = Offer::whereIn('id', $items->pluck('offer_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $problems = [];
            foreach ($items as $item) {
                $offer = $offers[$item->offer_id];
                $problem = match (true) {
                    $offer->state !== OfferState::Published || $offer->valid_from->isFuture() || $offer->valid_to->isPast() => 'oferta nie jest już ważna',
                    $offer->price_grosze !== $item->price_grosze || $offer->terms_version !== $item->terms_version => 'zmieniły się warunki oferty',
                    $offer->free_qty < $item->qty => "dostępna ilość to {$offer->free_qty}, zamówiono {$item->qty}",
                    default => null,
                };
                $problem === null || $problems[] = "{$item->name}: $problem.";
            }
            $problems === [] || throw ValidationException::withMessages(['order' => $problems]);

            foreach ($items->sortBy('offer_id') as $item) {
                $offer = $offers[$item->offer_id];
                $offer->forceFill(['free_qty' => $offer->free_qty - $item->qty, 'reserved_qty' => $offer->reserved_qty + $item->qty, 'version' => $offer->version + 1])->save();
                StockMovement::create([
                    'shop_id' => $offer->shop_id, 'offer_id' => $offer->id, 'kind' => 'reserve', 'delta_free' => -$item->qty, 'delta_reserved' => $item->qty,
                    'free_after' => $offer->free_qty, 'reserved_after' => $offer->reserved_qty, 'actor_id' => $actor->id,
                    'reason' => "Zamówienie #{$order->id}", 'occurred_at' => now(),
                ]);
                Reservation::create(['order_item_id' => $item->id, 'shop_id' => $offer->shop_id, 'offer_id' => $offer->id, 'qty' => $item->qty, 'held_at' => now()]);
            }

            $order->ean13 = $this->ean->next($order->location);
            $order->decided_at = now();
            $this->orders->move($order, OrderState::Confirmed, $actor);
            $this->orders->notify($order, 'confirmed');

            return $order;
        });
    }
}
