<?php

namespace App\View;

use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Support\Str;

final class CatalogDetails
{
    public static function description(CatalogEntry $entry): string
    {
        $field = $entry->area->freeTextColumns()[0] ?? '';

        return trim($entry->description ?? $entry->fields[$field] ?? '');
    }

    public static function preview(CatalogEntry $entry): string
    {
        $text = Str::squish(html_entity_decode(strip_tags(self::description($entry)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $sentences = preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', $text, 3) ?: [$text];

        return Str::limit(implode(' ', array_slice($sentences, 0, 2)), 300, '…', preserveWords: true);
    }

    /** @return array<string, string> */
    public static function parameters(CatalogEntry $entry, bool $full): array
    {
        $primary = match ($entry->area) {
            Area::Fauna => ['Wielkość', 'Temperatura', 'pH', 'WielkośćAkwarium'],
            Area::Flora => ['Wielkość', 'Trudność', 'CO2', 'WielkośćAkwarium'],
            Area::Equipment => ['kategoria_glowna', 'rodzaj', 'Marka', 'WielkośćAkwarium'],
            Area::Care => ['kategoria_zadania', 'rodzaj_zadania', 'obszar', 'Marka'],
        };
        $labels = [
            'WielkośćAkwarium' => 'Wielkość akwarium',
            'TypAkwarium' => 'Typ akwarium',
            'CO2' => 'CO₂',
            'kategoria_glowna' => 'Kategoria',
            'kategoria_zadania' => 'Kategoria zadania',
            'rodzaj_zadania' => 'Rodzaj zadania',
            'Poziom_doświadczenia' => 'Poziom doświadczenia',
            'Zdalne_sterowanie' => 'Zdalne sterowanie',
        ];
        $keys = $full
            ? array_unique([...$primary, ...array_diff(array_keys($entry->fields), [
                'id', ...array_filter($entry->area->nameColumns()), ...$entry->area->freeTextColumns(),
            ])])
            : $primary;

        $parameters = [];
        foreach ($keys as $key) {
            $value = trim($entry->fields[$key] ?? '');
            if ($value !== '') {
                $parameters[$labels[$key] ?? Str::ucfirst($key)] = str_replace(';', ', ', $value);
            }
        }

        return $parameters;
    }
}
