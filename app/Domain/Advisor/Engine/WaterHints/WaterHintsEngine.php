<?php

namespace App\Domain\Advisor\Engine\WaterHints;

use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TemperatureBand;

final class WaterHintsEngine
{
    /** @var array<string, array{label: string, unit: string, catalog: bool}> */
    private const PARAMETERS = [
        'ph' => ['label' => 'pH', 'unit' => '', 'catalog' => true],
        'temp_c' => ['label' => 'Temperatura', 'unit' => ' °C', 'catalog' => true],
        'kh' => ['label' => 'KH', 'unit' => ' °dKH', 'catalog' => false],
        'gh' => ['label' => 'GH', 'unit' => ' °dGH', 'catalog' => false],
        'no3' => ['label' => 'NO₃', 'unit' => ' mg/l', 'catalog' => false],
        'po4' => ['label' => 'PO₄', 'unit' => ' mg/l', 'catalog' => false],
    ];

    /**
     * @param  array<string, ?float>  $readings
     * @param  list<FaunaRecord>  $fauna
     * @param  list<string>  $checks
     * @param  array<string, array<string, SpecRange>>  $specsByFauna
     * @return list<Hint>
     */
    public function assess(
        array $readings,
        array $fauna,
        int $unknown,
        array $checks,
        ?int $batch = null,
        array $specsByFauna = [],
    ): array {
        $hints = [];
        foreach (self::PARAMETERS as $key => $meta) {
            if (! in_array($key, $checks, true) || ! array_key_exists($key, $readings)) {
                continue;
            }

            $reading = $readings[$key];
            if ($meta['catalog']) {
                $hints[] = $this->compareCatalog($key === 'ph', $reading, $fauna, $unknown, $batch, $specsByFauna);

                continue;
            }

            if ($reading === null) {
                continue;
            }

            $hints[] = $this->compareStructured($key, $meta['label'], $meta['unit'], $reading, $fauna, $unknown, $batch, $specsByFauna);
        }

        return $hints;
    }

    /**
     * Wspólny niesprzeczny zakres katalogowy dla obsady fauny (null = brak pełnych lub spójnych danych).
     *
     * @param  list<FaunaRecord>  $fauna
     * @param  list<string>  $checks
     * @param  array<string, array<string, SpecRange>>  $specsByFauna
     * @return array<string, list<array{min: ?float, max: ?float, label: string}>|null>
     */
    public function commonRanges(
        array $fauna,
        int $unknown,
        array $checks,
        ?int $batch = null,
        array $specsByFauna = [],
    ): array {
        $result = [];
        foreach (self::PARAMETERS as $key => $meta) {
            if (! in_array($key, $checks, true)) {
                continue;
            }

            if ($meta['catalog']) {
                $resolved = $this->resolveCatalogParameter($key === 'ph', $fauna, $unknown, $batch, null, $specsByFauna);
                if ($resolved['differences'] !== [] || $resolved['names'] === [] || $resolved['unknown'] > 0 || $resolved['common'] === []) {
                    $result[$key] = null;

                    continue;
                }

                $result[$key] = $this->exportRanges($resolved['common'], $meta['unit']);

                continue;
            }

            $resolved = $this->resolveStructuredParameter($key, $meta['label'], $meta['unit'], $fauna, $unknown, $batch, null, $specsByFauna);
            if ($resolved['names'] === []) {
                continue;
            }

            $result[$key] = $resolved['unknown'] > 0 || $resolved['common'] === []
                ? null
                : $this->exportRanges($resolved['common'], $meta['unit']);
        }

        return $result;
    }

    /**
     * @param  list<FaunaRecord>  $fauna
     * @param  array<string, array<string, SpecRange>>  $specsByFauna
     */
    private function compareCatalog(
        bool $ph,
        ?float $reading,
        array $fauna,
        int $unknown,
        ?int $batch,
        array $specsByFauna,
    ): Hint {
        $key = $ph ? 'ph' : 'temp_c';
        $column = $ph ? 'pH' : 'Temperatura';
        $unit = $ph ? '' : ' °C';
        if ($reading === null) {
            return new Hint(
                "$column — brak odczytu.",
                phProduct: $ph ? PhProductCheck::MissingReading : null,
                parameter: $key,
            );
        }

        $prefix = $column.' '.str_replace('.', ',', (string) $reading).$unit.' — ';
        $resolved = $this->resolveCatalogParameter($ph, $fauna, $unknown, $batch, $reading, $specsByFauna);
        $names = $resolved['names'];
        $bandsByName = $resolved['bandsByName'];
        $evidence = $resolved['evidence'];
        $differences = $resolved['differences'];
        $common = $resolved['common'];
        $unknown = $resolved['unknown'];
        $confirmed = $resolved['confirmed'];
        $phSourceConflict = $resolved['phSourceConflict'];
        $aboveAll = $resolved['aboveAll'];
        $belowAll = $resolved['belowAll'];

        if ($differences !== []) {
            return new Hint(
                $prefix.'brak danych do porównania. Źródła w katalogu się różnią — sprawdź u sprzedawcy. '.implode('; ', $differences).'.',
                $evidence,
                phProduct: $ph ? PhProductCheck::ConflictingSources : null,
                parameter: $key,
                productBlockReason: $ph ? PhProductCheck::ConflictingSources->value : null,
            );
        }
        if ($names === [] || $unknown > 0) {
            $scope = $names === [] ? 'dla tej obsady' : 'dla części obsady';

            return new Hint(
                $prefix."brak danych katalogowych $scope.",
                $evidence,
                phProduct: $ph ? PhProductCheck::IncompleteStock : null,
                parameter: $key,
                productBlockReason: $ph ? PhProductCheck::IncompleteStock->value : null,
            );
        }
        if (! $ph && Range::isBetweenTemperatureBands($reading)) {
            return new Hint(
                $prefix.'odczyt między pasmami katalogu. Pasma dla: '.implode(', ', $bandsByName).'.',
                $evidence,
                reviewStock: $common === [],
                parameter: $key,
            );
        }
        if ($common === []) {
            return new Hint(
                $prefix.'pasma w katalogu nie mają części wspólnej: '.implode(', ', $bandsByName).'.',
                $evidence,
                reviewStock: true,
                phProduct: $ph ? PhProductCheck::NoCommonRange : null,
                parameter: $key,
                productBlockReason: $ph ? PhProductCheck::NoCommonRange->value : null,
            );
        }

        $directions = array_map(fn (Range $range) => $range->compare($reading), $common);
        $position = match (true) {
            in_array(0, $directions, true) => 'w zakresie podanym w katalogu',
            min($directions) > 0 => 'powyżej zakresu podanego w katalogu',
            max($directions) < 0 => 'poniżej zakresu podanego w katalogu',
            default => 'odczyt między pasmami katalogu',
        };

        $productCheck = match (true) {
            ! $ph => null,
            $phSourceConflict => PhProductCheck::ConflictingSources,
            ! $confirmed => PhProductCheck::UnconfirmedRanges,
            ! $aboveAll => PhProductCheck::NotAboveAll,
            default => PhProductCheck::Eligible,
        };

        $productDirection = null;
        if ($ph && ! $phSourceConflict && $confirmed) {
            if ($aboveAll) {
                $productDirection = 'down';
            } elseif ($belowAll) {
                $productDirection = 'up';
            }
        }

        return new Hint(
            $prefix.$position.' dla: '.implode(', ', $names).' ('.$this->labels($common, $unit).').',
            $evidence,
            phProduct: $productCheck,
            parameter: $key,
            productDirection: $productDirection,
        );
    }

    /**
     * @param  list<FaunaRecord>  $fauna
     * @param  array<string, array<string, SpecRange>>  $specsByFauna
     */
    private function compareStructured(
        string $key,
        string $column,
        string $unit,
        float $reading,
        array $fauna,
        int $unknown,
        ?int $batch,
        array $specsByFauna,
    ): Hint {
        $prefix = $column.' '.str_replace('.', ',', (string) $reading).$unit.' — ';
        $resolved = $this->resolveStructuredParameter($key, $column, $unit, $fauna, $unknown, $batch, $reading, $specsByFauna);
        $names = $resolved['names'];
        $bandsByName = $resolved['bandsByName'];
        $evidence = $resolved['evidence'];
        $common = $resolved['common'];
        $unknown = $resolved['unknown'];
        $sourceLabels = $resolved['sourceLabels'];
        $aboveAll = $resolved['aboveAll'];
        $belowAll = $resolved['belowAll'];

        if ($names === [] || $unknown > 0) {
            $scope = $names === [] ? 'dla tej obsady' : 'dla części obsady';

            return new Hint(
                $prefix."brak danych {$scope}.",
                $evidence,
                parameter: $key,
                productBlockReason: "brak danych {$scope}",
            );
        }

        if ($common === []) {
            return new Hint(
                $prefix.'zakresy nie mają części wspólnej: '.implode(', ', $bandsByName).'.',
                $evidence,
                reviewStock: true,
                parameter: $key,
                productBlockReason: "brak wspólnego zakresu {$column} dla obsady",
            );
        }

        $directions = array_map(fn (Range $range) => $range->compare($reading), $common);
        $position = match (true) {
            in_array(0, $directions, true) => 'w zakresie',
            min($directions) > 0 => 'powyżej zakresu',
            default => 'poniżej zakresu',
        };
        $sourcesText = implode(', ', array_unique($sourceLabels));
        $productDirection = match (true) {
            $aboveAll => 'down',
            $belowAll => 'up',
            default => null,
        };

        return new Hint(
            $prefix."{$position} ({$sourcesText}) dla: ".implode(', ', $names).' ('.$this->labels($common, $unit).').',
            $evidence,
            parameter: $key,
            productDirection: $productDirection,
        );
    }

    /**
     * @param  list<FaunaRecord>  $fauna
     * @param  array<string, array<string, SpecRange>>  $specsByFauna
     * @return array{
     *     names: list<string>,
     *     bandsByName: list<string>,
     *     evidence: list<Evidence>,
     *     differences: list<string>,
     *     common: list<Range>,
     *     unknown: int,
     *     confirmed: bool,
     *     phSourceConflict: bool,
     *     aboveAll: bool,
     *     belowAll: bool
     * }
     */
    private function resolveCatalogParameter(
        bool $ph,
        array $fauna,
        int $unknown,
        ?int $batch,
        ?float $reading,
        array $specsByFauna,
    ): array {
        $key = $ph ? 'ph' : 'temp_c';
        $column = $ph ? 'pH' : 'Temperatura';
        $unit = $ph ? '' : ' °C';
        $common = [new Range(-INF, INF)];
        $evidence = $names = $bandsByName = $differences = [];
        $confirmed = $aboveAll = $belowAll = true;
        $phSourceConflict = false;

        foreach ($fauna as $record) {
            $bands = $ph ? $record->ph : ($record->temperature === null ? [] : [$record->temperature]);
            $hasBands = $bands !== [] && ! ($ph && $record->hasUnknownPhBand);
            $spec = $specsByFauna[$record->id][$key] ?? null;

            if ($spec !== null) {
                $specRange = new Range($spec->min, $spec->max);
                $specLabel = $specRange->label($unit);
                $specEvidence = new Evidence(
                    $column,
                    "{$specLabel} — „{$spec->evidenceQuote}”",
                    $record->id,
                    $batch,
                    $spec->sourceDescription(),
                );

                if ($hasBands) {
                    $ranges = array_map(Range::forBand(...), $bands);
                    $labels = $this->labels($ranges, $unit);
                    $names[] = $record->name;
                    $evidence[] = new Evidence($column, implode('; ', array_map(fn (PhBand|TemperatureBand $band) => $band->label(), $bands)), $record->id, $batch);
                    $evidence[] = $specEvidence;

                    $compatible = false;
                    foreach ($ranges as $bandRange) {
                        if ($bandRange->contains($specRange)) {
                            $compatible = true;

                            break;
                        }
                    }

                    if (! $compatible) {
                        $bandsByName[] = "{$record->name} ($labels)";
                        $differences[] = "{$record->name} (pasmo w katalogu: $labels, {$spec->sourceLabel}: {$specLabel})";
                        $confirmed = false;
                        $phSourceConflict = $phSourceConflict || $ph;
                        $common = $this->intersect($common, $ranges);

                        continue;
                    }

                    $bandsByName[] = "{$record->name} ({$specLabel})";
                    $effectiveRanges = [$specRange];
                } else {
                    $names[] = $record->name;
                    $bandsByName[] = "{$record->name} ({$specLabel})";
                    $evidence[] = $specEvidence;
                    $effectiveRanges = [$specRange];
                }

                if ($reading !== null) {
                    $aboveAll = $aboveAll && min(array_map(fn (Range $range) => $range->compare($reading), $effectiveRanges)) > 0;
                    $belowAll = $belowAll && max(array_map(fn (Range $range) => $range->compare($reading), $effectiveRanges)) < 0;
                }
                $common = $this->intersect($common, $effectiveRanges);

                continue;
            }

            if ($bands === [] || ($ph && $record->hasUnknownPhBand)) {
                $unknown++;

                continue;
            }

            $ranges = array_map(Range::forBand(...), $bands);
            $labels = $this->labels($ranges, $unit);
            $names[] = $record->name;
            $bandsByName[] = "{$record->name} ($labels)";
            $evidence[] = new Evidence($column, implode('; ', array_map(fn (PhBand|TemperatureBand $band) => $band->label(), $bands)), $record->id, $batch);
            $described = count($ranges) === 1 ? DescriptionRange::extract($record->description, $ph) : null;
            if ($described !== null && ! $ranges[0]->hasBounds($described[1], $described[2])) {
                $excerpt = $described[0];
                $differences[] = "{$record->name} (pasmo w katalogu: $labels, opis: „{$excerpt}”)";
                $evidence[] = new Evidence('Opis', $excerpt, $record->id, $batch);
            } elseif ($ph) {
                $described = count($ranges) === 1 ? DescriptionRange::extract($record->description, true, forProduct: true) : null;
                $agrees = $described !== null && $ranges[0]->hasBounds($described[1], $described[2]);
                $confirmed = $confirmed && $agrees;
                $phSourceConflict = $phSourceConflict || ($described !== null && ! $agrees);
                if ($described !== null) {
                    $evidence[] = new Evidence('Opis', $described[0], $record->id, $batch);
                }
                if ($reading !== null) {
                    $aboveAll = $aboveAll && min(array_map(fn (Range $range) => $range->compare($reading), $ranges)) > 0;
                    $belowAll = $belowAll && max(array_map(fn (Range $range) => $range->compare($reading), $ranges)) < 0;
                }
            }

            $common = $this->intersect($common, $ranges);
        }

        return [
            'names' => $names,
            'bandsByName' => $bandsByName,
            'evidence' => $evidence,
            'differences' => $differences,
            'common' => $common,
            'unknown' => $unknown,
            'confirmed' => $confirmed,
            'phSourceConflict' => $phSourceConflict,
            'aboveAll' => $aboveAll,
            'belowAll' => $belowAll,
        ];
    }

    /**
     * @param  list<FaunaRecord>  $fauna
     * @param  array<string, array<string, SpecRange>>  $specsByFauna
     * @return array{
     *     names: list<string>,
     *     bandsByName: list<string>,
     *     evidence: list<Evidence>,
     *     sourceLabels: list<string>,
     *     common: list<Range>,
     *     unknown: int,
     *     aboveAll: bool,
     *     belowAll: bool
     * }
     */
    private function resolveStructuredParameter(
        string $key,
        string $column,
        string $unit,
        array $fauna,
        int $unknown,
        ?int $batch,
        ?float $reading,
        array $specsByFauna,
    ): array {
        $common = [new Range(-INF, INF)];
        $evidence = $names = $bandsByName = $sourceLabels = [];
        $aboveAll = $belowAll = true;

        foreach ($fauna as $record) {
            $spec = $specsByFauna[$record->id][$key] ?? null;
            if ($spec === null) {
                $unknown++;

                continue;
            }

            $range = new Range($spec->min, $spec->max);
            $label = $range->label($unit);
            $names[] = $record->name;
            $bandsByName[] = "{$record->name} ({$label})";
            $sourceLabels[] = $spec->sourceLabel;
            $evidence[] = new Evidence(
                $column,
                "{$label} — „{$spec->evidenceQuote}”",
                $record->id,
                $batch,
                $spec->sourceDescription(),
            );

            if ($reading !== null) {
                $aboveAll = $aboveAll && $range->compare($reading) > 0;
                $belowAll = $belowAll && $range->compare($reading) < 0;
            }
            $common = $this->intersect($common, [$range]);
        }

        return [
            'names' => $names,
            'bandsByName' => $bandsByName,
            'evidence' => $evidence,
            'sourceLabels' => $sourceLabels,
            'common' => $common,
            'unknown' => $unknown,
            'aboveAll' => $aboveAll,
            'belowAll' => $belowAll,
        ];
    }

    /**
     * @param  list<Range>  $ranges
     * @return list<array{min: ?float, max: ?float, label: string}>
     */
    private function exportRanges(array $ranges, string $unit): array
    {
        return array_map(
            fn (Range $range): array => [
                'min' => $range->minBound(),
                'max' => $range->maxBound(),
                'label' => $range->label($unit),
            ],
            $ranges,
        );
    }

    /** @param list<Range> $ranges */
    private function labels(array $ranges, string $unit): string
    {
        return implode(' lub ', array_map(fn (Range $range) => $range->label($unit), $ranges));
    }

    /**
     * @param  list<Range>  $common
     * @param  list<Range>  $ranges
     * @return list<Range>
     */
    private function intersect(array $common, array $ranges): array
    {
        $overlaps = [];
        foreach ($common as $left) {
            foreach ($ranges as $right) {
                $overlap = $left->intersect($right);
                if ($overlap !== null) {
                    $overlaps[] = $overlap;
                }
            }
        }

        return $overlaps;
    }
}
