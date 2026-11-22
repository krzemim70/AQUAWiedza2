<?php

namespace App\Domain\Advisor\Engine;

/** Zapotrzebowanie rośliny na CO2 (kolumna CO2 flory). */
enum Co2Need: string
{
    use OrderedBand;

    case Unneeded = 'unneeded';
    case Recommended = 'recommended';
    case Required = 'required';

    public function label(): string
    {
        return match ($this) {
            self::Unneeded => 'Zbędny',
            self::Recommended => 'Wskazany',
            self::Required => 'Niezbędny',
        };
    }
}
