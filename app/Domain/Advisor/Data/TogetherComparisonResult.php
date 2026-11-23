<?php

namespace App\Domain\Advisor\Data;

use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use App\Domain\KnowledgeBase\Data\CatalogEntry;

/** Wynik porównania katalogowego wybranych gatunków fauny (bez zapisu w bazie). */
final readonly class TogetherComparisonResult
{
    /**
     * @param  list<string>  $selectedIds
     * @param  list<CatalogEntry>  $species
     * @param  list<string>  $unknownIds
     * @param  list<CatalogEntry>  $searchResults
     */
    public function __construct(
        public int $minSpecies,
        public int $maxSpecies,
        public array $selectedIds,
        public array $species,
        public array $unknownIds,
        public bool $tooMany,
        public bool $tooFew,
        public ?CompatibilityReport $report,
        public array $searchResults,
        public int $searchMinChars,
    ) {}
}
