<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\EffectivePrice;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\PriceHistory;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ustawia (albo zastępuje jeszcze niezaczętą) promocję oferty: cena promocyjna niższa od regularnej, okno od-do
 * o ograniczonej długości. Trwającą promocję można tylko zakończyć. Promocja nie zmienia warunków zamówień w toku.
 */
class SetOfferPromotion
{
    private const START_GRACE_MINUTES = 5;

    public function __construct(private Access $access, private Settings $settings, private PriceHistory $history) {}

    public function handle(User $actor, Offer $offer, int $priceGrosze, DateTimeInterface $from, DateTimeInterface $to): Offer
    {
        $this->access->authorizeMember($actor, $offer->shop_id);
        $maxDays = (int) $this->settings->get(SettingKey::ShopPromo)['max_days'];
        $from = Carbon::instance($from)->setTimezone(config('app.timezone')); // zapis w strefie bazy
        $to = Carbon::instance($to)->setTimezone(config('app.timezone'));

        return DB::transaction(function () use ($offer, $priceGrosze, $from, $to, $maxDays) {
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);
            $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

            EffectivePrice::promoActive($offer) && $fail('promo_price', 'Trwającą promocję można tylko zakończyć.');
            $priceGrosze >= 1 && $priceGrosze < $offer->price_grosze || $fail('promo_price', 'Cena promocyjna musi być niższa od ceny regularnej.');
            $from->lt(now()->subMinutes(self::START_GRACE_MINUTES)) && $fail('promo_from', 'Promocja nie może zaczynać się w przeszłości.');
            $to->gt($from) || $fail('promo_to', 'Koniec promocji musi być po jej początku.');
            $from->copy()->addDays($maxDays)->gte($to) || $fail('promo_to', "Promocja może trwać najwyżej $maxDays dni.");

            $this->history->close($offer, now(), 'promo'); // zastępowana promocja jeszcze się nie zaczęła
            $offer->forceFill(['promo_price_grosze' => $priceGrosze, 'promo_from' => $from->lt(now()) ? now() : $from, 'promo_to' => $to])->save();
            $offer->state === OfferState::Published && $this->history->openPromo($offer);

            return $offer;
        });
    }
}
