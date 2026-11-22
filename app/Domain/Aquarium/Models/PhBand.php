<?php

namespace App\Domain\Aquarium\Models;

/** Preferowane pH; wartości to etykiety z katalogu (pH). */
enum PhBand: string
{
    case Acidic = 'Woda kwaśna (poniżej 6.8)';
    case Neutral = 'Woda obojętna (6.8 - 7.5)';
    case Alkaline = 'Woda zasadowa (powyżej 7.5)';
}
