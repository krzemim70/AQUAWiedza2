<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Wycofanie blokuje nowe rezerwacje i zachowuje istniejące (rezerwacje pojawią się w kroku z koszykiem). */
class WithdrawOffer
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, Offer $offer): Offer
    {
        $this->access->authorizeMember($actor, $offer->shop_id);

        return DB::transaction(function () use ($offer) {
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);
            if ($offer->state !== OfferState::Published) {
                throw ValidationException::withMessages(['offer' => 'Wycofać można tylko opublikowaną ofertę.']);
            }

            $offer->forceFill(['state' => OfferState::Withdrawn, 'version' => $offer->version + 1])->save();

            return $offer;
        });
    }
}
