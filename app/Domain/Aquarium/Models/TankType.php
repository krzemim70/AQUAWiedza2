<?php

namespace App\Domain\Aquarium\Models;

/** Typ akwarium; wartości to etykiety z katalogu i Doradcy (TypAkwarium). */
enum TankType: string
{
    case Nano = 'Nano / Krewetkarium';
    case General = 'Ogólne / Towarzyskie';
    case Planted = 'Roślinne';
    case HardwaterBiotope = 'Biotop Twardowodny (Malawi/Tanganika)';
    case Specialist = 'Specjalistyczne';
}
