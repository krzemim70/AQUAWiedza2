<?php

namespace App\Domain\Shops;

use App\Domain\Shops\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Numer zamówienia EAN-13: `2` + numer placówki (4 cyfry) + sekwencja globalna (7 cyfr) + cyfra kontrolna.
 * Wywoływać w transakcji decyzji; błąd wycofuje całą decyzję.
 */
class EanNumber
{
    private const MAX_SEQUENCE = 9_999_999;

    private const MAX_LOCATION = 9_999;

    public function next(Location $location): string
    {
        // licznik blokowany jako ostatni; pod tą blokadą bezpieczne jest też nadawanie numeru placówki
        $last = (int) DB::table('shop_ean_sequence')->where('id', 1)->lockForUpdate()->value('last');
        $sequence = $last + 1;
        $location = Location::lockForUpdate()->findOrFail($location->id);
        $locationNo = $location->ean_no ?? ((int) Location::max('ean_no') + 1);

        if ($sequence > self::MAX_SEQUENCE || $locationNo > self::MAX_LOCATION) {
            Log::critical('Wyczerpana numeracja EAN zamówień.', ['sequence' => $sequence, 'location_no' => $locationNo]);
            throw ValidationException::withMessages(['ean' => 'Nie można nadać numeru zamówienia (wyczerpana numeracja). Administrator został powiadomiony.']);
        }

        DB::table('shop_ean_sequence')->where('id', 1)->update(['last' => $sequence]);
        if ($location->ean_no === null) {
            $location->forceFill(['ean_no' => $locationNo])->save();
        }

        $digits = '2'.str_pad((string) $locationNo, 4, '0', STR_PAD_LEFT).str_pad((string) $sequence, 7, '0', STR_PAD_LEFT);

        return $digits.self::checkDigit($digits);
    }

    /** Cyfra kontrolna z 12 cyfr: wagi 1 i 3 od lewej. */
    public static function checkDigit(string $digits12): int
    {
        $sum = 0;
        foreach (str_split($digits12) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }
}
