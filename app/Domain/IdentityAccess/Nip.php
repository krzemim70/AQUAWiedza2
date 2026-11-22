<?php

namespace App\Domain\IdentityAccess;

final class Nip
{
    /** Zostawia same cyfry. */
    public static function normalize(string $nip): string
    {
        return preg_replace('/\D/', '', $nip) ?? '';
    }

    /** 10 cyfr i suma kontrolna (wagi 6,5,7,2,3,4,5,6,7 mod 11). */
    public static function isValid(string $nip): bool
    {
        $nip = self::normalize($nip);
        if (strlen($nip) !== 10) {
            return false;
        }
        $sum = 0;
        foreach ([6, 5, 7, 2, 3, 4, 5, 6, 7] as $i => $w) {
            $sum += $w * (int) $nip[$i];
        }

        return $sum % 11 === (int) $nip[9];
    }
}
