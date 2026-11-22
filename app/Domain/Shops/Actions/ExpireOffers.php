<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use Illuminate\Support\Facades\DB;

/** Harmonogram: oznacza wygaśnięcie. Odczyt i tak sprawdza `valid_to`, więc spóźnienie harmonogramu niczego nie ujawnia. */
class ExpireOffers
{
    public function handle(): int
    {
        return Offer::where('state', OfferState::Published->value)->where('valid_to', '<=', now())
            ->update(['state' => OfferState::Expired->value, 'version' => DB::raw('version + 1')]);
    }
}
