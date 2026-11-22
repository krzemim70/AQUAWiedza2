<?php

namespace App\Domain\Advisor\Engine;

/** Strefa pływania (informacja pomocnicza). */
enum Zone: string
{
    use OrderedBand;

    case Bottom = 'bottom';
    case Middle = 'middle';
    case Top = 'top';

    public function label(): string
    {
        return match ($this) {
            self::Bottom => 'Dolna',
            self::Middle => 'Środkowa',
            self::Top => 'Górna',
        };
    }
}
