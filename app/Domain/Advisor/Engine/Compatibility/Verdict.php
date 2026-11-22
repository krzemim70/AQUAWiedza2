<?php

namespace App\Domain\Advisor\Engine\Compatibility;

/** Trzy stany oceny, bez procentów. */
enum Verdict: string
{
    case Clear = 'clear';
    case Notes = 'notes';
    case Conflicts = 'conflicts';

    public function label(): string
    {
        return match ($this) {
            self::Clear => 'Brak zastrzeżeń według bazy',
            self::Notes => 'Są uwagi',
            self::Conflicts => 'Są konflikty',
        };
    }
}
