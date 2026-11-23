<?php

namespace App\Domain\Shops;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Models\Offer;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Historia faktycznie stosowanych cen oferty: okresy ceny regularnej i okna promocji widoczne dla klientów.
 * Otwiera się przy publikacji i zmianie ceny, zamyka przy wycofaniu, wygaśnięciu, zakończeniu promocji.
 */
class PriceHistory
{
    public function __construct(private Settings $settings) {}

    private function rows(): Builder
    {
        return DB::table('shop_offer_price_history');
    }

    /** Oferta staje się widoczna (publikacja): cena regularna od teraz (lub od początku ważności) i trwająca promocja. */
    public function resume(Offer $offer): void
    {
        $from = $offer->valid_from->isFuture() ? $offer->valid_from : now();
        $this->rows()->insert(['offer_id' => $offer->id, 'kind' => 'regular', 'price_grosze' => $offer->price_grosze, 'valid_from' => $from]);
        if ($offer->promo_price_grosze !== null && $offer->promo_to > now()) {
            $this->openPromo($offer);
        }
    }

    public function openPromo(Offer $offer): void
    {
        $this->rows()->insert([
            'offer_id' => $offer->id, 'kind' => 'promo', 'price_grosze' => $offer->promo_price_grosze,
            'valid_from' => max($offer->promo_from, $offer->valid_from, now()), 'valid_to' => $offer->promo_to,
        ]);
    }

    /** Zamyka okresy trwające w chwili `$at` (i usuwa jeszcze niezaczęte): oferta przestała stosować te ceny. */
    public function close(Offer|int $offer, ?DateTimeInterface $at = null, ?string $kind = null): void
    {
        $at ??= now();
        $query = fn () => $this->rows()->where('offer_id', $offer instanceof Offer ? $offer->id : $offer)->when($kind, fn ($q) => $q->where('kind', $kind));
        $query()->where('valid_from', '>=', $at)->delete();
        $query()->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $at))->update(['valid_to' => $at]);
    }

    /** Zmiana ceny regularnej widocznej oferty: nowy okres od teraz. */
    public function changeRegular(Offer $offer): void
    {
        $this->close($offer, now(), 'regular');
        $this->rows()->insert(['offer_id' => $offer->id, 'kind' => 'regular', 'price_grosze' => $offer->price_grosze, 'valid_from' => now()]);
    }

    /**
     * Najniższa cena efektywna z okresu odniesienia przed startem aktywnej promocji (promocja jest zawsze tańsza od
     * regularnej, więc wystarczy minimum po okresach cen). Brak historii przed startem => brak wpisu.
     *
     * @param  iterable<Offer>  $offers  oferty z aktywną promocją
     * @return array<int, array{price: int, sinceStart: bool, days: int}>
     */
    public function references(iterable $offers): array
    {
        $days = (int) $this->settings->get(SettingKey::ShopPromo)['lookback_days'];
        $result = [];
        foreach ($offers as $offer) {
            $start = Carbon::instance($offer->promo_from);
            $since = $start->copy()->subDays($days);
            $rows = $this->rows()->where('offer_id', $offer->id)->where('valid_from', '<', $start)
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $since))->get();
            if ($rows->isEmpty()) {
                continue;
            }
            $first = Carbon::parse($this->rows()->where('offer_id', $offer->id)->where('kind', 'regular')->min('valid_from'), 'UTC');
            $result[$offer->id] = ['price' => (int) $rows->min('price_grosze'), 'sinceStart' => $first->gt($since), 'days' => $days];
        }

        return $result;
    }
}
