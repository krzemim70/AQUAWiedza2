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
            return new Hint("$column — brak odczytu.", phProduct: $ph ? PhProductCheck::MissingReading : null);
        }

        $prefix = $column.' '.str_replace('.', ',', (string) $reading).$unit.' — ';
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
            $described = count($ranges) === 1 ? $this->descriptionRange($record->description, $ph) : null;
            if ($described !== null && ! $ranges[0]->hasBounds($described[1], $described[2])) {
                $excerpt = $described[0];
                $differences[] = "{$record->name} (pasmo w katalogu: $labels, opis: „{$excerpt}”)";
                $evidence[] = new Evidence('Opis', $excerpt, $record->id, $batch);
            } elseif ($ph) {
                $described = count($ranges) === 1 ? $this->descriptionRange($record->description, true, forProduct: true) : null;
                $agrees = $described !== null && $ranges[0]->hasBounds($described[1], $described[2]);
                $confirmed = $confirmed && $agrees;
                $phSourceConflict = $phSourceConflict || ($described !== null && ! $agrees);
                if ($described !== null) {
                    $evidence[] = new Evidence('Opis', $described[0], $record->id, $batch);
                }
                $aboveAll = $aboveAll && min(array_map(fn (Range $range) => $range->compare($reading), $ranges)) > 0;
            }

            $common = $this->intersect($common, $ranges);
        }

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
     * shortcut: rozpoznajemy tylko jeden jednoznaczny przedział przy nazwie parametru; inne zapisy wymagają danych strukturalnych.
     *
     * @return array{string, float, float}|null
     */
    private function descriptionRange(?string $description, bool $ph, bool $forProduct = false): ?array
    {
        if ($description === null) {
            return null;
        }

        $parameter = $ph ? 'pH' : 'temperatura';
        $unit = match (true) {
            $forProduct => '(?![\d\p{L}]|[,.]\d|\h*(?:[°%/–—-]|(?:lub|do)\h+\d))',
            $ph => '(?!\d|[,.]\d|\h*[°%])',
            default => '\h*°\h*C\b',
        };
        $number = '(-?\d+(?:[,.]\d+)?)';
        $qualifier = $forProduct ? 'około|mniej\h+więcej' : 'około';
        preg_match_all("~\b$parameter\h*(?:[:=]\h*)?(?:(?:$qualifier)\h+)?$number\h*[-–—]\h*$number$unit~iu", $description, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $bounds = [];
        foreach ($matches as [[$excerpt, $offset], [$low], [$high]]) {
            $min = (float) str_replace(',', '.', $low);
            $max = (float) str_replace(',', '.', $high);
            if ($min > $max || ($ph && ($min < 0 || $max > 14))) {
                return null;
            }
            if ($forProduct && $this->negatesRange($description, $excerpt, $offset)) {
                return null;
            }
            $bounds["$min:$max"] = [$excerpt, $min, $max];
        }

        return count($bounds) === 1 ? array_values($bounds)[0] : null;
    }

    private function negatesRange(string $description, string $excerpt, int $offset): bool
    {
        [$before] = array_slice(preg_split('/[.!?]\s+/u', substr($description, 0, $offset)) ?: [''], -1);
        [$after] = preg_split('/[.!?]\s+/u', substr($description, $offset + strlen($excerpt)), 2) ?: [''];

        return preg_match('/\b(?:nie|niewskazan\p{L}*|unika\p{L}*|poza)\b/iu', "$before $after") === 1;
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
