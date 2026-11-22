<?php

namespace App\Domain\Aquarium\Models;

/** Strefy stosunku N:P w kolejności rosnącego N:P (granice z ustawień). */
enum RedfieldZone: string
{
    case StrongPhosphateExcess = 'strong_phosphate_excess';
    case PhosphateExcess = 'phosphate_excess';
    case Balanced = 'balanced';
    case NitrateExcess = 'nitrate_excess';
    case StrongNitrateExcess = 'strong_nitrate_excess';

    public function label(): string
    {
        return match ($this) {
            self::StrongPhosphateExcess => 'Silny nadmiar fosforanów',
            self::PhosphateExcess => 'Nadmiar fosforanów',
            self::Balanced => 'Zbilansowany',
            self::NitrateExcess => 'Nadmiar azotanów',
            self::StrongNitrateExcess => 'Silny nadmiar azotanów',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::StrongPhosphateExcess => 'Fosforanów jest zdecydowanie za dużo względem azotanów. Ogranicz źródła fosforu i zwiększ podmiany wody.',
            self::PhosphateExcess => 'Fosforanów jest nieco za dużo względem azotanów.',
            self::Balanced => 'Stosunek azotanów do fosforanów jest zbilansowany.',
            self::NitrateExcess => 'Azotanów jest nieco za dużo względem fosforanów.',
            self::StrongNitrateExcess => 'Azotanów jest zdecydowanie za dużo względem fosforanów. Rośliny mogą cierpieć na niedobór fosforu.',
        };
    }

    /** Semantyczny sygnał strefy (zielony, żółty, czerwony). */
    public function signal(): string
    {
        return match ($this) {
            self::Balanced => 'green',
            self::PhosphateExcess, self::NitrateExcess => 'yellow',
            default => 'red',
        };
    }
}
