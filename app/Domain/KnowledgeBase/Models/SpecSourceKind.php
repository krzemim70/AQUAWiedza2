<?php

namespace App\Domain\KnowledgeBase\Models;

enum SpecSourceKind: string
{
    case CatalogQuote = 'catalog_quote';
    case ManufacturerLabel = 'manufacturer_label';
    case Literature = 'literature';

    public function label(): string
    {
        return match ($this) {
            self::CatalogQuote => 'Katalog Aikwarium (cytat z opisu)',
            self::ManufacturerLabel => 'Etykieta producenta',
            self::Literature => 'Literatura (poza tabelą katalogu)',
        };
    }

    public function allowedFor(Area $area): bool
    {
        return match ($this) {
            self::CatalogQuote => true,
            self::ManufacturerLabel => in_array($area, [Area::Care, Area::Equipment], true),
            self::Literature => $area === Area::Fauna,
        };
    }

    public function requiresSourceRef(): bool
    {
        return $this !== self::CatalogQuote;
    }
}
