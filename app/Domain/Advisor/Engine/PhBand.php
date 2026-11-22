<?php

namespace App\Domain\Advisor\Engine;

enum PhBand: string
{
    use OrderedBand;

    case Acidic = 'acidic';
    case Neutral = 'neutral';
    case Alkaline = 'alkaline';

    public function label(): string
    {
        return match ($this) {
            self::Acidic => 'Woda kwaśna (poniżej 6.8)',
            self::Neutral => 'Woda obojętna (6.8 - 7.5)',
            self::Alkaline => 'Woda zasadowa (powyżej 7.5)',
        };
    }
}
