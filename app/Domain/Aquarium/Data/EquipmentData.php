<?php

namespace App\Domain\Aquarium\Data;

final readonly class EquipmentData
{
    public const MAX_QUANTITY = 9999;

    public function __construct(public string $catalogId, public int $quantity) {}
}
