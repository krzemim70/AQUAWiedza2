<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\EanNumber;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Orders;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Sklep odrzuca całe nowe zamówienie z kodem powodu (dla `inne` opis obowiązkowy); numer EAN identyfikuje sprawę. */
class RejectOrder
{
    public function __construct(private Access $access, private Orders $orders, private EanNumber $ean) {}

    public function handle(User $actor, Order $order, string $code, ?string $note = null): Order
    {
        Validator::make(['code' => $code, 'note' => $note], [
            'code' => ['required', 'in:'.implode(',', array_keys(Order::REJECTION_CODES))],
            'note' => [$code === 'inne' ? 'required' : 'nullable', 'string', 'max:255'],
        ], [], ['code' => 'powód', 'note' => 'opis'])->validate();

        return DB::transaction(function () use ($actor, $order, $code, $note) {
            $order = $this->orders->lock($order);
            $this->access->authorizeMember($actor, $order->shop_id);

            if ($order->state === OrderState::Rejected) {
                return $order;
            }
            $order->state === OrderState::New || throw ValidationException::withMessages(['order' => 'Zamówienie nie jest już nowe, więc nie można go odrzucić.']);

            $order->forceFill(['ean13' => $this->ean->next($order->location), 'decided_at' => now(), 'rejection_code' => $code, 'note' => $note]);
            $this->orders->move($order, OrderState::Rejected, $actor, Order::REJECTION_CODES[$code].($note ? ": $note" : ''));
            $this->orders->notify($order, 'rejected');

            return $order;
        });
    }
}
