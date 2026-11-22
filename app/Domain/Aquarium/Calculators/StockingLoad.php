<?php

namespace App\Domain\Aquarium\Calculators;

use App\Domain\Advisor\Engine\FishBand;

/** Orientacyjne obciążenie: suma (ilość × długość dorosłej ryby, cm) / litry. Reguła praktyczna, nie norma. */
final class StockingLoad
{
    /** Górna granica pasma Wielkość z bazy; „B. duża” nie ma długości (null = wpisuje użytkownik). */
    public static function maxLengthCm(?FishBand $band): ?int
    {
        return match ($band) {
            FishBand::Nano => 3,
            FishBand::Small => 5,
            FishBand::Medium => 10,
            FishBand::Large => 15,
            default => null,
        };
    }

    /** @param  list<array{quantity: int, length: float}>  $items */
    public static function totalCm(array $items): float
    {
        return array_sum(array_map(fn (array $i) => $i['quantity'] * $i['length'], $items));
    }

    public static function cmPerLiter(float $totalCm, float $liters): float
    {
        return $totalCm / $liters;
    }
}
