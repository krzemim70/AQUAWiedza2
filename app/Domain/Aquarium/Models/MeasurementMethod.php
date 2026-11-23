<?php

namespace App\Domain\Aquarium\Models;

/** Metoda wykonania pomiaru wody. */
enum MeasurementMethod: string
{
    case Strip = 'strip';
    case Drop = 'drop';
    case Meter = 'meter';
    case Sensor = 'sensor';

    public function label(): string
    {
        return match ($this) {
            self::Strip => 'Pasek',
            self::Drop => 'Test kropelkowy',
            self::Meter => 'Miernik',
            self::Sensor => 'Czujnik',
        };
    }
}
