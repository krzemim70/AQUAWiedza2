<?php

namespace App\Domain\Shops\Contracts;

use App\Domain\Shops\Actions\PseudonymizeOrders;
use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\MemberRole;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\RecordWatch;
use App\Domain\Shops\Models\Review;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountData
{
    public function __construct(private PseudonymizeOrders $pseudonymizeOrders) {}

    /** @return array{cart: list<\stdClass>, orders: list<array<string, mixed>>, record_watches: list<array<string, mixed>>, reviews: list<array<string, mixed>>} */
    public function exportFor(int $userId): array
    {
        $cart = DB::table('shop_cart_items')->where('user_id', $userId)->orderBy('offer_id')
            ->get(['shop_id', 'offer_id', 'qty', 'price_seen_grosze', 'created_at', 'updated_at'])->all();
        $orders = Order::where('user_id', $userId)->orderBy('id')->with([
            'contact:order_id,nick,email,phone',
            'items:id,order_id,offer_id,area,legacy_id,name,variant,price_grosze,terms_version,qty',
            'events:id,order_id,from_state,to_state,occurred_at',
        ])->get()->map(fn (Order $order) => Arr::only($order->toArray(), [
            'id', 'shop_id', 'location_id', 'state', 'total_grosze', 'ean13', 'rejection_code',
            'created_at', 'decided_at', 'finished_at', 'items', 'events',
        ]) + ['contact' => $order->contactVisible() ? $order->contact?->only(['nick', 'email', 'phone']) : null])->all();

        $watches = RecordWatch::where('user_id', $userId)->orderBy('id')
            ->get(['area', 'legacy_id', 'created_at', 'notified_at', 'removed_at'])->toArray();

        $reviews = Review::where('customer_id', $userId)->orderBy('id')
            ->get(['id', 'order_id', 'shop_id', 'rating', 'comment', 'hidden_reason', 'created_at', 'updated_at'])->toArray();

        return ['cart' => $cart, 'orders' => $orders, 'record_watches' => $watches, 'reviews' => $reviews];
    }

    /** Wywołać w transakcji usuwającej konto. Koszyk, członkostwa i obserwacje usuwa klucz obcy. */
    public function eraseFor(int $userId): void
    {
        $shops = Shop::whereIn('id', Member::where('user_id', $userId)->where('role', MemberRole::Owner)->select('shop_id'))
            ->orderBy('id')->lockForUpdate()->get();

        foreach ($shops as $shop) {
            if ($shop->state === ShopState::Active) {
                throw ValidationException::withMessages([
                    'deletion' => "Jesteś jedynym właścicielem aktywnego sklepu „{$shop->name}”. Przed usunięciem konta przekaż własność z pomocą operatora serwisu.",
                ]);
            }
        }

        $this->pseudonymizeOrders->handle($userId);
        Review::where('customer_id', $userId)->update(['customer_id' => null, 'order_id' => null, 'comment' => null]);
    }
}
