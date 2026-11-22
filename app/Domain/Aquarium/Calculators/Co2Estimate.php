<?php

namespace App\Domain\Aquarium\Calculators;

/** Przybliżone CO₂ [mg/l] z zależności węglanowej: współczynnik × KH [°dKH] × 10^(7 − pH). */
final class Co2Estimate
{
    public static function mgPerLiter(float $kh, float $ph, float $factor = 3.0): float
    {
        return $factor * $kh * 10 ** (7 - $ph);
    }
}
