<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Wstrzymanie (powód obowiązkowy) chowa oferty sklepu w publicznym odczycie i blokuje publikację. */
class SuspendShop
{
    public function handle(User $admin, Shop $shop, string $reason): Shop
    {
        Gate::forUser($admin)->authorize('manage-users');

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Podaj powód wstrzymania.']);
        }

        return DB::transaction(function () use ($admin, $shop, $reason) {
            $shop = Shop::lockForUpdate()->findOrFail($shop->id);
            if (! in_array($shop->state, [ShopState::Preparation, ShopState::Active], true)) {
                throw ValidationException::withMessages(['shop' => 'Ten sklep nie może zostać wstrzymany.']);
            }

            $shop->forceFill(['state' => ShopState::Suspended])->save();
            activity()->performedOn($shop)->causedBy($admin)->withProperties(['reason' => $reason])->log('shop suspended');

            return $shop;
        });
    }
}
