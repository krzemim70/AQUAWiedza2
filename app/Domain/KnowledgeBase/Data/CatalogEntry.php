<?php

namespace App\Domain\KnowledgeBase\Data;

use App\Domain\KnowledgeBase\Models\Area;

/** Rekord katalogu dla innych modułów (np. silnika doradcy): klucz, nazwy, surowe pola i znormalizowane wartości. */
final readonly class CatalogEntry
{
    /**
     * @param  array<string, string>  $fields  surowe wartości po nagłówku kolumny (jak wiersz starego pliku TSV)
     * @param  array<string, list<string>>  $values  te same kolumny rozbite po `;`
     * @param  list<array{url: string|null, author: string|null, licence: string|null}>  $photos  tylko zweryfikowane; wypełniane przez `find()`
     */
    public function __construct(
        public Area $area,
        public string $id,
        public string $name,
        public ?string $nameLatin,
        public array $fields,
        public array $values,
        public ?string $description = null,
        public array $photos = [],
    ) {}
}
