<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\PriceHistory;
use Illuminate\Support\Facades\DB;

/** Harmonogram: oznacza wygaśnięcie. Odczyt i tak sprawdza `valid_to`, więc spóźnienie harmonogramu niczego nie ujawnia. */
class ExpireOffers
{
    public function __construct(private PriceHistory $history) {}

    public function handle(): int
    {
        $due = Offer::where('state', OfferState::Published->value)->where('valid_to', '<=', now())->get();
        foreach ($due as $offer) {
            $this->history->close($offer, $offer->valid_to); // ceny przestały obowiązywać z końcem ważności
        }

        return Offer::whereIn('id', $due->modelKeys())->update(['state' => OfferState::Expired->value, 'version' => DB::raw('version + 1')]);
    }
}
