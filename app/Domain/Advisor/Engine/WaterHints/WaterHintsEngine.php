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

    /** @param list<FaunaRecord> $fauna */
    private function compare(bool $ph, ?float $reading, array $fauna, int $unknown, ?int $batch): Hint
    {
        $column = $ph ? 'pH' : 'Temperatura';
        $unit = $ph ? '' : ' °C';
        if ($reading === null) {
            return new Hint("$column — brak odczytu.");
        }

        $prefix = $column.' '.str_replace('.', ',', (string) $reading).$unit.' — ';
        $common = [new Range(-INF, INF)];
        $evidence = $names = $bandsByName = $differences = [];
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
            $excerpt = $this->differentDescriptionRange($record->description, $ph, $ranges);
            if ($excerpt !== null) {
                $differences[] = "{$record->name} (pasmo w katalogu: $labels, opis: „{$excerpt}”)";
                $evidence[] = new Evidence('Opis', $excerpt, $record->id, $batch);
            }

            $common = $this->intersect($common, $ranges);
        }

        if ($differences !== []) {
            return new Hint($prefix.'brak danych do porównania. Źródła w katalogu się różnią — sprawdź u sprzedawcy. '.implode('; ', $differences).'.', $evidence);
        }
        if ($names === [] || $unknown > 0) {
            $scope = $names === [] ? 'dla tej obsady' : 'dla części obsady';

            return new Hint($prefix."brak danych katalogowych $scope.", $evidence);
        }
        if (! $ph && Range::isBetweenTemperatureBands($reading)) {
            return new Hint($prefix.'odczyt między pasmami katalogu. Pasma dla: '.implode(', ', $bandsByName).'.', $evidence, reviewStock: $common === []);
        }
        if ($common === []) {
            return new Hint($prefix.'pasma w katalogu nie mają części wspólnej: '.implode(', ', $bandsByName).'.', $evidence, reviewStock: true);
        }

        $directions = array_map(fn (Range $range) => $range->compare($reading), $common);
        $position = match (true) {
            in_array(0, $directions, true) => 'w zakresie podanym w katalogu',
            min($directions) > 0 => 'powyżej zakresu podanego w katalogu',
            max($directions) < 0 => 'poniżej zakresu podanego w katalogu',
            default => 'odczyt między pasmami katalogu',
        };

        return new Hint($prefix.$position.' dla: '.implode(', ', $names).' ('.$this->labels($common, $unit).').', $evidence);
    }

    /**
     * shortcut: rozpoznajemy tylko jeden jednoznaczny przedział przy nazwie parametru; inne zapisy wymagają danych strukturalnych.
     *
     * @param  list<Range>  $ranges
     */
    private function differentDescriptionRange(?string $description, bool $ph, array $ranges): ?string
    {
        if ($description === null || count($ranges) !== 1) {
            return null;
        }

        $parameter = $ph ? 'pH' : 'temperatura';
        $unit = $ph ? '(?!\d|[,.]\d|\h*[°%])' : '\h*°\h*C\b';
        $number = '(-?\d+(?:[,.]\d+)?)';
        preg_match_all("/\b$parameter\h*(?:[:=]\h*)?(?:około\h+)?$number\h*[-–—]\h*$number$unit/iu", $description, $matches, PREG_SET_ORDER);
        $bounds = [];
        foreach ($matches as [$excerpt, $low, $high]) {
            $min = (float) str_replace(',', '.', $low);
            $max = (float) str_replace(',', '.', $high);
            if ($min > $max || ($ph && ($min < 0 || $max > 14))) {
                return null;
            }
            $bounds["$min:$max"] = [$excerpt, $min, $max];
        }
        if (count($bounds) !== 1) {
            return null;
        }
        [$excerpt, $min, $max] = array_values($bounds)[0];

        return $ranges[0]->hasBounds($min, $max) ? null : $excerpt;
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
