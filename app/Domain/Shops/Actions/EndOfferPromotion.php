<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\PriceHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Kończy promocję przed czasem: trwającą od teraz (okno trafia do historii), zaplanowaną usuwa bez śladu. */
class EndOfferPromotion
{
    public function __construct(private Access $access, private PriceHistory $history) {}

    public function handle(User $actor, Offer $offer): Offer
    {
        $this->access->authorizeMember($actor, $offer->shop_id);

        return DB::transaction(function () use ($offer) {
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);
            if ($offer->promo_to === null || $offer->promo_to <= now()) {
                throw ValidationException::withMessages(['promo_to' => 'Ta oferta nie ma trwającej ani zaplanowanej promocji.']);
            }

            $this->history->close($offer, now(), 'promo');
            $offer->promo_from > now()
                ? $offer->forceFill(['promo_price_grosze' => null, 'promo_from' => null, 'promo_to' => null])
                : $offer->forceFill(['promo_to' => now()]);
            $offer->save();

            return $offer;
        });
    }
}
