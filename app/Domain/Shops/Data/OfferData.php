<?php

namespace App\Domain\Shops\Data;

use App\Domain\KnowledgeBase\Models\Area;
use DateTimeInterface;

/** Wejście formularza oferty. Przy edycji produkt, placówka i początkowa ilość są ignorowane. */
final readonly class OfferData
{
    public function __construct(
        public int $locationId,
        public Area $area,
        public string $legacyId,
        public string $variant,
        public int $priceGrosze,
        public DateTimeInterface $validFrom,
        public DateTimeInterface $validTo,
        public int $freeQty = 0,
    ) {}
}
