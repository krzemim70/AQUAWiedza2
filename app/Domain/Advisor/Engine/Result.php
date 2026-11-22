<?php

namespace App\Domain\Advisor\Engine;

/** Wynik doboru obszarowego. */
final readonly class Result
{
    /**
     * @param  list<Card>  $cards  karty do pokazania (lider, grupa remisu, alternatywy)
     * @param  list<Card>  $shortlist  lista rezerwowa (zaczyna się od `$cards`)
     * @param  int  $ranked  liczba ocenionych rekordów
     * @param  list<int>  $hiddenPercents  procenty kolejnych rekordów, które nie weszły do kart (maks. 3)
     * @param  int  $tieCount  liczba równorzędnych na szczycie (1 = jeden zwycięzca)
     * @param  list<string>  $criteria  etykiety aktywnych kryteriów w kolejności planu
     * @param  bool  $insufficient  zero aktywnych kryteriów: „za mało danych do porównania”, procenty nie różnicują
     */
    public function __construct(
        public GuideArea $area,
        public string $label,
        public GroupType $type,
        public array $cards,
        public array $shortlist,
        public int $ranked,
        public array $hiddenPercents,
        public int $tieCount,
        public array $criteria,
        public ?string $notice,
        public bool $insufficient,
    ) {}
}
