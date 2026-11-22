<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Orders;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kolejny krok realizacji po zatwierdzeniu: Opłacone (potwierdzenie wpływu pełnej kwoty), W przygotowaniu,
 * Gotowe do odbioru, Odebrane (rezerwacje zużyte). Powtórzenie osiągniętego kroku nie ma skutku.
 */
class AdvanceOrder
{
    /** Stan docelowy => wymagany stan wyjściowy. */
    private const FROM = [
        'paid' => OrderState::Confirmed,
        'preparing' => OrderState::Paid,
        'ready' => OrderState::Preparing,
        'picked_up' => OrderState::Ready,
    ];

    public function __construct(private Access $access, private Orders $orders) {}

    public function handle(User $actor, Order $order, OrderState $to): Order
    {
        $from = self::FROM[$to->value] ?? throw ValidationException::withMessages(['order' => 'Nieobsługiwane przejście stanu.']);

        return DB::transaction(function () use ($actor, $order, $to, $from) {
            $order = $this->orders->lock($order);
            $this->access->authorizeMember($actor, $order->shop_id);

            if ($order->state === $to) {
                return $order;
            }
            $order->state === $from || throw ValidationException::withMessages(['order' => "Zamówienie jest w stanie „{$order->state->label()}”, więc nie można przejść do „{$to->label()}”."]);

            match ($to) {
                OrderState::Paid => DB::table('shop_order_payments')->insert([
                    'order_id' => $order->id, 'amount_grosze' => $order->total_grosze, 'confirmed_by' => $actor->id, 'confirmed_at' => now(),
                ]),
                OrderState::PickedUp => $this->orders->resolveReservations($order, 'consumed'),
                default => null,
            };
            $this->orders->move($order, $to, $actor);
            $to === OrderState::Ready && $this->orders->notify($order, 'ready');

            return $order;
        });
    }
}
