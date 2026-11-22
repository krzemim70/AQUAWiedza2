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
}
