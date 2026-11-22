<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Orders;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zamknięcie bez realizacji: klient (tylko nowe), członek sklepu (każde otwarte) albo administrator (wsparcie).
 * Powód obowiązkowy; po wpływie także rozliczenie poza platformą. Aktywne rezerwacje wracają do dostępnej ilości
 * w tej samej transakcji, dokładnie raz.
 */
class CloseOrder
{
    public function __construct(private Access $access, private Orders $orders) {}

    public function handle(User $actor, Order $order, string $reason, ?string $settlement = null): Order
    {
        $reason = trim($reason);
        mb_strlen($reason) >= 1 && mb_strlen($reason) <= 200 || throw ValidationException::withMessages(['reason' => 'Podaj powód zamknięcia (do 200 znaków).']);

        return DB::transaction(function () use ($actor, $order, $reason, $settlement) {
            $order = $this->orders->lock($order);
            $allowed = $actor->is_admin
                || $this->access->isMember($actor, $order->shop_id)
                || ($order->user_id === $actor->id && $order->state === OrderState::New);
            $allowed || throw new AuthorizationException('Nie możesz zamknąć tego zamówienia.');

            if ($order->state === OrderState::Closed) {
                return $order;
            }
            $order->state->isFinal() && throw ValidationException::withMessages(['order' => 'Zamówienie jest już zakończone.']);

            $paid = in_array($order->state, [OrderState::Paid, OrderState::Preparing, OrderState::Ready], true);
            $paid && trim((string) $settlement) === '' && throw ValidationException::withMessages(['settlement' => 'Wpływ został potwierdzony. Opisz rozliczenie poza platformą (np. zwrot przelewu).']);

            $order->state->holdsReservation() && $this->orders->resolveReservations($order, 'released');
            $order->note = $reason;
            $this->orders->move($order, OrderState::Closed, $actor, $reason.($paid ? ' | Rozliczenie: '.trim((string) $settlement) : ''));

            return $order;
        });
    }
}
