<?php

namespace App\Domain\Advisor\Engine;

/** Pasmo wielkości akwarium. Kod jest stabilny, etykieta pochodzi z katalogu. */
enum TankBand: string
{
    use OrderedBand;

    case Nano = 'nano';
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Huge = 'huge';

    public function label(): string
    {
        return match ($this) {
            self::Nano => 'Nano (< 40L)',
            self::Small => 'Małe (40L - 100L)',
            self::Medium => 'Średnie (101L - 250L)',
            self::Large => 'Duże (251L - 500L)',
            self::Huge => 'Bardzo duże (> 500L)',
        };
    }

    /** Pasmo dla litrażu; progi jak w etykietach: < 40, 40-100, 101-250, 251-500, > 500 l. */
    public static function forLiters(int $liters): self
    {
        return match (true) {
            $liters < 40 => self::Nano,
            $liters <= 100 => self::Small,
            $liters <= 250 => self::Medium,
            $liters <= 500 => self::Large,
            default => self::Huge,
        };
    }

    /** Reprezentatywny litraż pasma. */
    public function volume(): int
    {
        return match ($this) {
            self::Nano => 30,
            self::Small => 70,
            self::Medium => 175,
            self::Large => 375,
            self::Huge => 600,
        };
    }
}
