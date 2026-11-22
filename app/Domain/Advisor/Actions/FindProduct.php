<?php

namespace App\Domain\Advisor\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;

/** „Znajdź produkt”: wyszukanie rekordu po nazwie lub pełnym ID (limity z ustawień), trafienia po etykiecie. */
class FindProduct
{
    public function __construct(private Catalog $catalog) {}

    /** @return list<CatalogEntry> */
    public function handle(string $phrase, ?int $batch = null): array
    {
        $hits = $this->catalog->search($phrase, null, $batch);
        usort($hits, fn (CatalogEntry $a, CatalogEntry $b) => [Catalog::fold($a->name), $a->id] <=> [Catalog::fold($b->name), $b->id]);

        return $hits;
    }
}
