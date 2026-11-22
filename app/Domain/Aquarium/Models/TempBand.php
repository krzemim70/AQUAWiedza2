<?php

namespace App\Domain\Aquarium\Models;

/** Preferowana temperatura; wartości to etykiety z katalogu (Temperatura). */
enum TempBand: string
{
    case Cold = 'Zimnolubne (poniżej 18)';
    case Occasional = 'Grzałka sporadycznie (18-21,5)';
    case Often = 'Grzałka często (22-25,5)';
    case VeryOften = 'Grzałka b. często (26-30)';
}
