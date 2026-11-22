<?php

namespace App\Domain\Advisor\Engine;

/** Temperament z bazy wiedzy (tylko etykieta, bez informacji, kogo dotyczy). */
enum Temperament: string
{
    use OrderedBand;

    case Calm = 'calm';
    case Moderate = 'moderate';
    case Aggressive = 'aggressive';

    public function label(): string
    {
        return match ($this) {
            self::Calm => 'Spokojny',
            self::Moderate => 'Umiarkowany',
            self::Aggressive => 'Agresywny/Terytorialny',
        };
    }
}
