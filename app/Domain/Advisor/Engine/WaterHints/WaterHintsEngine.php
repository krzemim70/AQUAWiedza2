<?php

namespace App\Domain\Advisor\Engine\WaterHints;

use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TemperatureBand;

final class WaterHintsEngine
{
    /**
     * @param  array{ph: ?float, temp_c: ?float}  $readings
     * @param  list<FaunaRecord>  $fauna
     * @param  list<string>  $checks
     * @return list<Hint>
     */
    public function assess(array $readings, array $fauna, int $unknown, array $checks, ?int $batch = null): array
    {
        $hints = [];
        foreach ($readings as $key => $reading) {
            if (in_array($key, $checks, true)) {
                $hints[] = $this->compare($key === 'ph', $reading, $fauna, $unknown, $batch);
            }
        }

        return $hints;
    }

    /**
     * Wspólny niesprzeczny zakres katalogowy dla obsady fauny (null = brak pełnych lub spójnych danych).
     *
     * @param  list<FaunaRecord>  $fauna
     * @param  list<string>  $checks
     * @return array<string, list<array{min: ?float, max: ?float, label: string}>|null>
     */
    public function commonRanges(array $fauna, int $unknown, array $checks, ?int $batch = null): array
    {
        $result = [];
        foreach (['ph' => true, 'temp_c' => false] as $key => $ph) {
            if (! in_array($key, $checks, true)) {
                continue;
            }
            $resolved = $this->resolveParameter($ph, $fauna, $unknown, $batch, null);
            if ($resolved['differences'] !== [] || $resolved['names'] === [] || $resolved['unknown'] > 0 || $resolved['common'] === []) {
                $result[$key] = null;

                continue;
            }

            $unit = $ph ? '' : ' °C';
            $result[$key] = array_map(
                fn (Range $range): array => [
                    'min' => $range->minBound(),
                    'max' => $range->maxBound(),
                    'label' => $range->label($unit),
                ],
                $resolved['common'],
            );
        }

        return $result;
    }

    /** @param list<FaunaRecord> $fauna */
    private function compare(bool $ph, ?float $reading, array $fauna, int $unknown, ?int $batch): Hint
    {
        $column = $ph ? 'pH' : 'Temperatura';
        $unit = $ph ? '' : ' °C';
        if ($reading === null) {
            return new Hint("$column — brak odczytu.", phProduct: $ph ? PhProductCheck::MissingReading : null);
        }

        $prefix = $column.' '.str_replace('.', ',', (string) $reading).$unit.' — ';
        $resolved = $this->resolveParameter($ph, $fauna, $unknown, $batch, $reading);
        $names = $resolved['names'];
        $bandsByName = $resolved['bandsByName'];
        $evidence = $resolved['evidence'];
        $differences = $resolved['differences'];
        $common = $resolved['common'];
        $unknown = $resolved['unknown'];
        $confirmed = $resolved['confirmed'];
        $phSourceConflict = $resolved['phSourceConflict'];
        $aboveAll = $resolved['aboveAll'];

        if ($differences !== []) {
            return new Hint($prefix.'brak danych do porównania. Źródła w katalogu się różnią — sprawdź u sprzedawcy. '.implode('; ', $differences).'.', $evidence, phProduct: $ph ? PhProductCheck::ConflictingSources : null);
        }
        if ($names === [] || $unknown > 0) {
            $scope = $names === [] ? 'dla tej obsady' : 'dla części obsady';

            return new Hint($prefix."brak danych katalogowych $scope.", $evidence, phProduct: $ph ? PhProductCheck::IncompleteStock : null);
        }
        if (! $ph && Range::isBetweenTemperatureBands($reading)) {
            return new Hint($prefix.'odczyt między pasmami katalogu. Pasma dla: '.implode(', ', $bandsByName).'.', $evidence, reviewStock: $common === []);
        }
        if ($common === []) {
            return new Hint($prefix.'pasma w katalogu nie mają części wspólnej: '.implode(', ', $bandsByName).'.', $evidence, reviewStock: true, phProduct: $ph ? PhProductCheck::NoCommonRange : null);
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

        return new Hint($prefix.$position.' dla: '.implode(', ', $names).' ('.$this->labels($common, $unit).').', $evidence, phProduct: $productCheck);
    }

    /**
     * @param  list<FaunaRecord>  $fauna
     * @return array{
     *     names: list<string>,
     *     bandsByName: list<string>,
     *     evidence: list<Evidence>,
     *     differences: list<string>,
     *     common: list<Range>,
     *     unknown: int,
     *     confirmed: bool,
     *     phSourceConflict: bool,
     *     aboveAll: bool
     * }
     */
    private function resolveParameter(bool $ph, array $fauna, int $unknown, ?int $batch, ?float $reading): array
    {
        $column = $ph ? 'pH' : 'Temperatura';
        $unit = $ph ? '' : ' °C';
        $common = [new Range(-INF, INF)];
        $evidence = $names = $bandsByName = $differences = [];
        $confirmed = $aboveAll = true;
        $phSourceConflict = false;
        foreach ($fauna as $record) {
            $bands = $ph ? $record->ph : ($record->temperature === null ? [] : [$record->temperature]);
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
        ];
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
