<?php

namespace App\Domain\Shops;

use App\Domain\Shops\Models\Offer;
use DateTimeInterface;

/** Jedyna reguła ceny efektywnej: cena promocyjna, gdy od <= t < do; w przeciwnym razie regularna. */
final class EffectivePrice
{
    public static function promoActive(Offer $offer, ?DateTimeInterface $at = null): bool
    {
        $at ??= now();

        return $offer->promo_price_grosze !== null && $offer->promo_from !== null && $offer->promo_to !== null
            && $offer->promo_from <= $at && $at < $offer->promo_to;
    }

    public static function of(Offer $offer, ?DateTimeInterface $at = null): int
    {
        return self::promoActive($offer, $at) ? $offer->promo_price_grosze : $offer->price_grosze;
    }
}
