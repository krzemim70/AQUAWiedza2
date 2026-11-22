<?php

namespace App\Domain\Advisor\Engine;

/** Pasmo wielkości ryby. */
enum FishBand: string
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
            self::Nano => 'Nano (do 3 cm)',
            self::Small => 'Mała (3-5 cm)',
            self::Medium => 'Średnia (5-10 cm)',
            self::Large => 'Duża (10-15 cm)',
            self::Huge => 'B. duża (>15 cm)',
        };
    }

    /** Wielkość ryby „dopasowana do zbiornika”: pasmo o tym samym indeksie. */
    public static function matching(TankBand $tank): self
    {
        return self::cases()[$tank->index()];
    }
}
