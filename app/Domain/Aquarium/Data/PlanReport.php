<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;

/** Widok planu: pozycje z rekordami katalogu, porównanie katalogowe obsady i sprzęt do przeglądania. */
final readonly class PlanReport
{
    /**
     * @param  list<array{area: Area, id: string, quantity: int, entry: CatalogEntry|null}>  $lines
     * @param  array<string, int>  $categories  kategoria sprzętu => liczba pasujących rekordów katalogu
     * @param  list<CatalogEntry>  $equipment  rekordy wybranej kategorii (puste, gdy kategorii nie wybrano)
     */
    public function __construct(
        public array $lines,
        public CompatibilityReport $compatibility,
        public array $categories,
        public array $equipment,
        public ?string $category,
    ) {}
}
