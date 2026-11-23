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
            self::Clear => 'Porównanie wpisów katalogu',
            self::Notes, self::Conflicts => 'Różnice w danych katalogowych',
        };
    }
}
