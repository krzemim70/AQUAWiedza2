<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Wznowienie wraca do aktywnego (po pierwszej aktywacji) albo do przygotowania. */
class ResumeShop
{
    public function __construct(private EvaluateShop $evaluate) {}

    public function handle(User $admin, Shop $shop, string $reason): Shop
    {
        Gate::forUser($admin)->authorize('manage-users');

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Podaj powód wznowienia.']);
        }

        return DB::transaction(function () use ($admin, $shop, $reason) {
            $shop = Shop::lockForUpdate()->findOrFail($shop->id);
            if ($shop->state !== ShopState::Suspended) {
                throw ValidationException::withMessages(['shop' => 'Ten sklep nie jest wstrzymany.']);
            }

            $shop->forceFill(['state' => $shop->activated_at ? ShopState::Active : ShopState::Preparation])->save();
            activity()->performedOn($shop)->causedBy($admin)->withProperties(['reason' => $reason])->log('shop resumed');

            return $this->evaluate->handle($shop);
        });
    }
}
