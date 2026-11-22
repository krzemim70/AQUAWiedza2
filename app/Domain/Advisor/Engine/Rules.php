<?php

namespace App\Domain\Advisor\Engine;

/** Reguły kreatora jako wartości (z ustawień, bez stałych w silniku). */
final readonly class Rules
{
    /**
     * @param  list<int>  $fit  progi etykiet dopasowania (rosnąco: warunkowe, wysokie, bardzo wysokie)
     * @param  list<int>  $verification  progi werdyktu (rosnąco: warunkowy, zgodny)
     * @param  int  $cards  liczba kart wyniku
     * @param  int  $shortlist  liczba pozycji listy rezerwowej wyniku
     * @param  bool  $includeTies  przy remisie najlepszych pokaż wszystkie równorzędne karty
     * @param  array{ph_acid: list<string>, ph_other: list<string>, co2: list<string>, heating_category: string, percent: int}  $corrections
     */
    public function __construct(
        public array $fit,
        public array $verification,
        public int $cards,
        public int $shortlist,
        public bool $includeTies,
        public array $corrections,
    ) {}
}
