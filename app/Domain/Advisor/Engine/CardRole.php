<?php

namespace App\Domain\Advisor\Engine;

enum CardRole: string
{
    case Main = 'main';
    case Alternative = 'alternative';
    case Tie = 'tie';
    case Better = 'better';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Rekomendacja główna',
            self::Alternative => 'Mocna alternatywa',
            self::Tie => 'Wynik ex aequo',
            self::Better => 'Lepsza alternatywa',
            self::Correction => 'Produkt poprawiający dopasowanie',
        };
    }
}
