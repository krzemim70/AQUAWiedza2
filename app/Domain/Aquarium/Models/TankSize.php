<?php

namespace App\Domain\Aquarium\Models;

/** Pasma wielkości zbiornika; wartości to etykiety z katalogu i Doradcy (WielkośćAkwarium). */
enum TankSize: string
{
    case Nano = 'Nano (< 40L)';
    case Small = 'Małe (40L - 100L)';
    case Medium = 'Średnie (101L - 250L)';
    case Large = 'Duże (251L - 500L)';
    case VeryLarge = 'Bardzo duże (> 500L)';

    /** Reprezentatywny litraż pasma. */
    public function volume(): int
    {
        return match ($this) {
            self::Nano => 30,
            self::Small => 70,
            self::Medium => 175,
            self::Large => 375,
            self::VeryLarge => 600,
        };
    }
}
