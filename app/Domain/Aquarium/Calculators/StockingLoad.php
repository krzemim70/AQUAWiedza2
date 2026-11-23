<?php

namespace App\Domain\Aquarium\Calculators;

/** Orientacyjne obciążenie: suma (ilość × długość dorosłej ryby, cm) / litry. Reguła praktyczna, nie norma. */
final class StockingLoad
{
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
