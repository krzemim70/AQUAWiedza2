<?php

namespace App\Domain\Shops\Data;

use App\Domain\KnowledgeBase\Models\Area;
use DateTimeInterface;

/**
 * Widoczna oferta dla innych modułów (doradca, koszyk). `freeQty` = dostępna ilość; 0 oznacza niedostępną.
 * Ocena i liczba opinii sklepu są dostępne od ustawionego progu.
 */
final readonly class PublicOffer
{
    public function __construct(
        public int $id,
        public Area $area,
        public string $legacyId,
        public string $variant,
        public int $priceGrosze,
        public int $freeQty,
        public DateTimeInterface $validFrom,
        public DateTimeInterface $validTo,
        public int $shopId,
        public string $shopName,
        public int $locationId,
        public string $locationName,
        public string $locationAddress,
        public string $locationCity,
        public int $termsVersion = 1,
        public ?int $regularPriceGrosze = null,
        public ?DateTimeInterface $promoEndsAt = null,
        public ?float $shopRatingAvg = null,
        public int $shopRatingCount = 0,
    ) {}
}
