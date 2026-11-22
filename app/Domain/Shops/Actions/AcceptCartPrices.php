<?php

namespace App\Domain\Shops\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Klient akceptuje bieżące ceny pozycji koszyka (po zmianie ceny przez sklep). */
class AcceptCartPrices
{
    public function __construct(private ListCart $cart) {}

    public function handle(User $user): void
    {
        foreach ($this->cart->handle($user) as $line) {
            DB::table('shop_cart_items')->where('user_id', $user->id)->where('offer_id', $line->offerId)->update(['price_seen_grosze' => $line->priceGrosze]);
        }
    }
}
