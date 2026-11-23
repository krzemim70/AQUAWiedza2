<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Data\PublicOffer;

/** Wiersz oferty w podziale listy zakupów po placówkach. */
final readonly class ShoppingOfferLine
{
    public function __construct(
        public Area $area,
        public string $id,
        public string $name,
        public int $wanted,
        public int $offerId,
        public string $variant,
        public int $freeQty,
        public int $takeQty,
        public int $priceGrosze,
        public PublicOffer $offer,
    ) {}
}
