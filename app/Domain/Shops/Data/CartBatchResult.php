<?php

namespace App\Domain\Shops\Data;

/**
 * Wynik grupowego dodania ofert placówki do koszyka wraz z listą pozycji o niepełnej dostępności.
 */
final readonly class CartBatchResult
{
    /**
     * @param  list<array{offerId: int, name: string, wanted: int, added: int, missing: int, reason: string}>  $shortfalls
     */
    public function __construct(
        public int $addedLines,
        public int $addedUnits,
        public array $shortfalls = [],
    ) {}

    public function message(): string
    {
        if ($this->addedLines === 0) {
            if ($this->shortfalls === []) {
                return 'Nie wybrano pozycji do dodania do koszyka.';
            }
            $details = array_map(fn (array $s) => "{$s['name']} ({$s['reason']})", $this->shortfalls);

            return 'Nie udało się dodać pozycji do koszyka — brak dostępności: '.implode('; ', $details).'.';
        }

        if ($this->shortfalls === []) {
            return 'Dodano wybrane pozycje do koszyka.';
        }

        $parts = array_map(
            fn (array $s) => "{$s['name']} — dodano {$s['added']} z {$s['wanted']} szt. (brakuje {$s['missing']} szt.)",
            $this->shortfalls,
        );

        return 'Dodano dostępne pozycje do koszyka. Niepełna dostępność: '.implode('; ', $parts).'.';
    }
}
