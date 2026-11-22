<?php

namespace App\Domain\Aquarium\Models;

enum ReminderType: string
{
    case WaterChange = 'water_change';
    case FilterCleaning = 'filter_cleaning';
    case Fertilization = 'fertilization';
    case WaterTest = 'water_test';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WaterChange => 'Podmiana wody',
            self::FilterCleaning => 'Czyszczenie filtra',
            self::Fertilization => 'Nawożenie',
            self::WaterTest => 'Test wody',
            self::Other => 'Inne',
        };
    }
}
