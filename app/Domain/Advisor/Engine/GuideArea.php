<?php

namespace App\Domain\Advisor\Engine;

/** Obszar katalogu w kreatorze (wartości jak w `KnowledgeBase\Models\Area`, ale silnik nie zna modułu bazy wiedzy). */
enum GuideArea: string
{
    case Fauna = 'fauna';
    case Flora = 'flora';
    case Equipment = 'equipment';
    case Care = 'care';

    public function label(): string
    {
        return match ($this) {
            self::Fauna => 'Fauna',
            self::Flora => 'Flora',
            self::Equipment => 'Sprzęt',
            self::Care => 'Pielęgnacja',
        };
    }
}
