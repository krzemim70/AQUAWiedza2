<?php

namespace App\Domain\Advisor\Engine;

enum TemperatureBand: string
{
    use OrderedBand;

    case Cold = 'cold';
    case Cool = 'cool';
    case Warm = 'warm';
    case Hot = 'hot';

    public function label(): string
    {
        return match ($this) {
            self::Cold => 'Zimnolubne (poniżej 18)',
            self::Cool => 'Grzałka sporadycznie (18-21,5)',
            self::Warm => 'Grzałka często (22-25,5)',
            self::Hot => 'Grzałka b. często (26-30)',
        };
    }
}
