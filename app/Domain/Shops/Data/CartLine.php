<?php

namespace App\Domain\Shops\Data;

/**
 * Pozycja koszyka z bieżącymi danymi oferty. `issue` (jeśli jest) blokuje wysyłkę i mówi, co poprawić.
 */
final readonly class CartLine
{
    public function __construct(
        public int $offerId,
        public int $shopId,
        public string $shopName,
        public int $locationId,
        public string $locationName,
        public string $locationAddress,
        public string $area,
        public string $legacyId,
        public string $name,
        public string $variant,
        public int $qty,
        public int $priceGrosze,
        public int $priceSeenGrosze,
        public int $freeQty,
        public int $termsVersion,
        public ?string $issue,
        public ?ShopRating $shopRating = null,
        public ?int $regularPriceGrosze = null,
        public ?PublicOffer $offer = null,
    ) {}

    public function total(): int
    {
        return $this->priceGrosze * $this->qty;
    }
}
