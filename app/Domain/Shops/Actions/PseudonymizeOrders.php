<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\Checkout;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Orders;
use Illuminate\Support\Facades\DB;

/**
 * Po terminie (domyślnie 90 dni od stanu końcowego) lub przy usunięciu konta usuwa migawkę kontaktu, odłącza klienta i czyści
 * opisy wolnym tekstem. EAN, daty, stany, kwoty i pozycje zostają. Powtarzalne; odczyt i tak ukrywa kontakt po terminie.
 */
class PseudonymizeOrders
{
    public function __construct(private Orders $orders) {}

    public function handle(?int $userId = null): int
    {
        $count = 0;
        $query = Order::whereNull('pseudonymized_at');
        $ids = ($userId === null ? $query->where('pseudonymize_at', '<=', now()) : $query->where('user_id', $userId))
            ->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$count) {
                $order = Order::lockForUpdate()->find($id);
                if ($order === null || $order->pseudonymized_at !== null) {
                    return;
                }
                $order->contact()->delete();
                $order->events()->update(['reason' => null]);
                $order->events()->where('actor_id', $order->user_id)->update(['actor_id' => null]);
                $order->forceFill(['user_id' => null, 'note' => null, 'pseudonymized_at' => now()]);
                $order->version++;
                $order->save();
                $this->orders->event($order, $order->state, null, 'Pseudonimizacja danych klienta');
                $count++;
            });
        }

        // wysyłka bez zamówień z klientem nie musi już wskazywać użytkownika
        $checkouts = Checkout::whereNotNull('user_id');
        if ($userId !== null) {
            $checkouts->where('user_id', $userId);
        }
        $checkouts->whereDoesntHave('orders', fn ($q) => $q->whereNull('pseudonymized_at'))->update(['user_id' => null]);

        return $count;
    }
}
