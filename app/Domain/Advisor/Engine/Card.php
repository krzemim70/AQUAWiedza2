<?php

namespace App\Domain\Advisor\Engine;

/** Karta wyniku: odwołanie do rekordu (obszar + ID), ocena i uzasadnienie. */
final readonly class Card
{
    /**
     * @param  array<string, int>  $scores  oceny 0–3 po krótkim kluczu kryterium (np. `size`, `ph`), tylko aktywne kryteria
     * @param  list<string>  $reasons
     * @param  list<string>  $warnings
     */
    public function __construct(
        public CardRole $role,
        public GuideArea $area,
        public string $id,
        public string $name,
        public int $percent,
        public int $points,
        public int $maxPoints,
        public array $scores,
        public array $reasons,
        public array $warnings,
        public ?string $fit = null,
    ) {}
}
