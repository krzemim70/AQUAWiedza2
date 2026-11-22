<?php

namespace App\Domain\Aquarium\Calculators;

/** Skalowanie dawki z etykiety producenta: dawka_etykiety × V / V_etykiety. Żadnych własnych dawek. */
final class Dosage
{
    public static function scale(float $labelDose, float $labelVolume, float $volume): float
    {
        return $labelDose * $volume / $labelVolume;
    }
}
