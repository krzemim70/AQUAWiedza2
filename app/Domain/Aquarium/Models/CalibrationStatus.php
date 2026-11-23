<?php

namespace App\Domain\Aquarium\Models;

/** Status kalibracji sondy / miernika przy odczycie pomiaru. */
enum CalibrationStatus: string
{
    case Ok = 'ok';
    case Due = 'due';
    case Uncalibrated = 'uncalibrated';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Skalibrowana',
            self::Due => 'Wymaga kalibracji',
            self::Uncalibrated => 'Nieskalibrowana',
        };
    }
}
