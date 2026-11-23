<?php

namespace App\Domain\KnowledgeBase\Data;

use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use Carbon\CarbonInterface;

/** Zweryfikowana specyfikacja strukturalna rekordu katalogu dla innych modułów. */
final readonly class StructuredSpec
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public Area $area,
        public string $id,
        public SpecSourceKind $sourceKind,
        public ?string $sourceRef,
        public string $evidenceQuote,
        public ?string $ean,
        public array $attributes,
        public ?CarbonInterface $verifiedAt,
    ) {}

    /**
     * Zakres parametru dla fauny (ph, temp_c, kh, gh, no3, po4) albo null przy braku danych.
     *
     * @return array{min: float, max: float}|null
     */
    public function range(string $parameter): ?array
    {
        if ($this->area !== Area::Fauna) {
            return null;
        }
        $range = $this->attributes[$parameter] ?? null;

        return is_array($range) && isset($range['min'], $range['max'])
            ? ['min' => (float) $range['min'], 'max' => (float) $range['max']]
            : null;
    }

    /**
     * Przeciwwskazania preparatu z cytatami (każde: target + quote).
     *
     * @return list<array{target: string, quote: string}>
     */
    public function contraindications(): array
    {
        if ($this->area !== Area::Care) {
            return [];
        }
        $items = $this->attributes['contraindications'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item) && is_string($item['target'] ?? null) && is_string($item['quote'] ?? null)) {
                $result[] = ['target' => $item['target'], 'quote' => $item['quote']];
            }
        }

        return $result;
    }
}
