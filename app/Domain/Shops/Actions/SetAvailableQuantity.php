<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Podaje NOWĄ dostępną ilość (nie przyrost). Blokada wiersza oferty, ilość zarezerwowana pozostaje bez zmian,
 * jeden wpis historii. Nieaktualna wersja oznacza, że ktoś zmienił ilość w międzyczasie.
 */
class SetAvailableQuantity
{
    public function __construct(private Access $access, private Settings $settings) {}

    public function handle(User $actor, Offer $offer, int $expectedVersion, int $newQty, bool $physicalConfirmed, ?string $reason = null): Offer
    {
        $this->access->authorizeMember($actor, $offer->shop_id);

        if (! $physicalConfirmed) {
            throw ValidationException::withMessages(['confirmed' => 'Potwierdź, że dostępna ilość zgadza się ze stanem fizycznym.']);
        }
        $max = (int) $this->settings->get(SettingKey::ShopOfferBounds)['max_qty'];

        return DB::transaction(function () use ($actor, $offer, $expectedVersion, $newQty, $reason, $max) {
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);

            if ($offer->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => "Ilość została w międzyczasie zmieniona (teraz: {$offer->free_qty}). Sprawdź i spróbuj ponownie."]);
            }
            if ($newQty < 0 || $newQty + $offer->reserved_qty > $max) {
                throw ValidationException::withMessages(['qty' => "Dostępna ilość wraz z zarezerwowaną ({$offer->reserved_qty}) musi mieścić się w zakresie 0–$max."]);
            }

            StockMovement::create([
                'shop_id' => $offer->shop_id, 'offer_id' => $offer->id, 'kind' => 'adjust',
                'delta_free' => $newQty - $offer->free_qty, 'free_after' => $newQty, 'reserved_after' => $offer->reserved_qty,
                'actor_id' => $actor->id, 'reason' => $reason, 'occurred_at' => now(),
            ]);
            $offer->forceFill(['free_qty' => $newQty, 'version' => $offer->version + 1])->save();

            return $offer;
        });
    }
}
