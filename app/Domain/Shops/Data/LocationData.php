<?php

namespace App\Domain\Shops\Data;

final readonly class LocationData
{
    public function __construct(
        public string $name,
        public string $address,
        public string $city,
        public ?string $phone = null,
        public ?string $openingHours = null,
        public bool $active = true,
    ) {}
}
