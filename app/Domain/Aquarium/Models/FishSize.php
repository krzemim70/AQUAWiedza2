<?php

namespace App\Domain\Aquarium\Models;

/** Preferowana wielkość ryby; wartości to etykiety z katalogu (Wielkość fauny). */
enum FishSize: string
{
    case Nano = 'Nano (do 3 cm)';
    case Small = 'Mała (3-5 cm)';
    case Medium = 'Średnia (5-10 cm)';
    case Large = 'Duża (10-15 cm)';
    case VeryLarge = 'B. duża (>15 cm)';
}
