<?php

namespace App\Domain\KnowledgeBase\Contracts;

use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

class WaterHintProducts
{
    public function __construct(private Catalog $catalog, private Settings $settings) {}

    /** @return array<string, CatalogEntry|null> Rozpoznane preparaty i pominięcia według ID wpisu słownika. */
    public function loweringPh(int $batch): array
    {
        $mappings = array_filter($this->settings->get(SettingKey::WaterHintProducts), fn (array $mapping) => $mapping['direction'] === 'ph_down');
        if ($mappings === []) {
            return [];
        }

        $entries = array_column($this->catalog->snapshot(Area::Care, $batch, array_column($mappings, 'id'), withDescriptions: true), null, 'id');
        $matches = [];
        foreach ($mappings as ['id' => $id, 'quote' => $quote]) {
            $entry = $entries[$id] ?? null;
            $matches[$id] = $entry !== null && ($entry->fields['obszar'] ?? null) === 'Suplementy'
                && ($entry->fields['rodzaj_zadania'] ?? null) !== 'Testowanie wody'
                && $quote !== '' && $quote === ($entry->fields['opis'] ?? null)
                && ($entry->description === null || $quote === $entry->description)
                    ? $entry : null;
        }

        return $matches;
    }
}
