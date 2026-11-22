<?php

namespace App\Domain\Aquarium\Calculators;

/** Objętość brutto (litry) z wymiarów w centymetrach; wysokość to poziom lustra wody. */
final class TankVolume
{
    public static function cuboid(float $length, float $width, float $height): float
    {
        return $length * $width * $height / 1000;
    }

    public static function cylinder(float $diameter, float $height): float
    {
        return M_PI * ($diameter / 2) ** 2 * $height / 1000;
    }
}
