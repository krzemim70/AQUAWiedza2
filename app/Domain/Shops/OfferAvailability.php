<?php

namespace App\Domain\Shops;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\Shops\Data\PublicOffer;

final class OfferAvailability
{
    public static function cityKey(?string $city): ?string
    {
        $key = trim(Catalog::fold($city ?? ''));

        return $key === '' ? null : $key;
    }

    /** @param list<PublicOffer> $offers */
    public static function firstIn(array $offers, ?string $cityKey): ?PublicOffer
    {
        foreach ($offers as $offer) {
            if ($offer->freeQty > 0 && ($cityKey === null || self::cityKey($offer->locationCity) === $cityKey)) {
                return $offer;
            }
        }

        return null;
    }
}
