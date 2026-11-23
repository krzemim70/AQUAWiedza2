<?php

namespace App\Domain\Aquarium\Data;

/** Oferty jednej placówki sklepu wraz z metrykami pokrycia planu i szacunkiem ceny. */
final readonly class ShoppingLocationGroup
{
    /**
     * @param  list<ShoppingOfferLine>  $lines
     */
    public function __construct(
        public int $locationId,
        public int $shopId,
        public string $shop,
        public string $location,
        public string $address,
        public string $city,
        public int $coveredCount,
        public int $fullyCoveredCount,
        public int $totalGrosze,
        public array $lines,
    ) {}
}
