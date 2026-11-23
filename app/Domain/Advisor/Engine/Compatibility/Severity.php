<?php

namespace App\Domain\Advisor\Engine\Compatibility;

/** Ważność kontroli zgodności (ustawienie `aquarium.compat.severity`). */
enum Severity: string
{
    case Conflict = 'conflict';
    case Warning = 'warning';
    case Info = 'info';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Conflict => 'Konflikt',
            self::Warning => 'Uwaga',
            self::Info => 'Informacja',
            self::Off => 'Wyłączona',
        };
    }
}
