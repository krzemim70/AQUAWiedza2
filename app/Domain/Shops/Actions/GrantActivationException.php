<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\ActivationException;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Odstępstwo pilota zastępuje próg ofert w podanym czasie; weryfikacja, placówka i limit ofert obowiązują dalej. */
class GrantActivationException
{
    public function __construct(private EvaluateShop $evaluate) {}

    public function handle(User $admin, Shop $shop, DateTimeInterface $from, DateTimeInterface $to, string $reason): ActivationException
    {
        Gate::forUser($admin)->authorize('manage-users');

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Podaj powód odstępstwa.']);
        }

        return DB::transaction(function () use ($admin, $shop, $from, $to, $reason) {
            $shop = Shop::lockForUpdate()->findOrFail($shop->id);
            if (! in_array($shop->state, [ShopState::Preparation, ShopState::Active, ShopState::Suspended], true)) {
                throw ValidationException::withMessages(['shop' => 'Dla tego sklepu nie można przyznać odstępstwa.']);
            }
            if ($to <= $from) {
                throw ValidationException::withMessages(['effective_to' => 'Koniec odstępstwa musi być po jego początku.']);
            }
            if ($shop->activationExceptions()->where('effective_from', '<', $to)->where('effective_to', '>', $from)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'W tym czasie obowiązuje już odstępstwo.']);
            }

            $exception = $shop->activationExceptions()->create([
                'effective_from' => $from, 'effective_to' => $to, 'reason' => $reason, 'granted_by' => $admin->id,
            ]);
            activity()->performedOn($shop)->causedBy($admin)->withProperties(['reason' => $reason])->log('activation exception granted');
            $this->evaluate->handle($shop);

            return $exception;
        });
    }
}
