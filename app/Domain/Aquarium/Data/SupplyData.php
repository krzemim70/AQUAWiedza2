<?php

namespace App\Domain\Aquarium\Data;

final readonly class SupplyData
{
    public function __construct(
        public string $catalogId,
        public string $unit,
        public string $packageAmount,
        public string $doseAmount,
        public ?string $remainingAmount = null,
        public ?string $dosePerLiters = null,
        public ?int $intervalDays = null,
        public ?int $preferredShopId = null,
    ) {}
}
