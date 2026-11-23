<?php

namespace App\Domain\Aquarium\Models;

/** Źródło pochodzenia odczytu pomiaru wody. */
enum MeasurementSource: string
{
    case Manual = 'manual';
    case Device = 'device';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Ręczny',
            self::Device => 'Urządzenie',
            self::Import => 'Import',
        };
    }
}
