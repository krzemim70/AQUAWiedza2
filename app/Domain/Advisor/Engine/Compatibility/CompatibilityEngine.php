<?php

namespace App\Domain\Advisor\Engine\Compatibility;

use App\Domain\Advisor\Engine\Co2Need;
use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\FloraRecord;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\WaterHints\DescriptionRange;
use App\Domain\Advisor\Engine\WaterHints\Range;

/**
 * Czysty silnik zgodności obsady (projekt nowych funkcji, §2). Każde ustalenie niesie dowód z rekordu bazy;
 * kontrola bez danych liczy się jako „brak danych”, nie jako zgodna. Bez kandydata sprawdza całą obsadę,
 * z kandydatem tylko to, co dotyczy kandydata.
 */
final class CompatibilityEngine
{
    /** @param array<string, Severity> $severity */
    public function __construct(private array $severity) {}

    /** @param list<FaunaRecord|FloraRecord> $stock */
    public function check(TankContext $tank, array $stock, FaunaRecord|FloraRecord|null $candidate = null): CompatibilityReport
    {
        $outcomes = [];
        foreach ($candidate === null ? $stock : [$candidate] as $record) {
            array_push($outcomes, ...$this->tankChecks($record, $tank));
        }
        if ($candidate === null) {
            for ($i = 0; $i < count($stock); $i++) {
                for ($j = $i + 1; $j < count($stock); $j++) {
                    array_push($outcomes, ...$this->pairChecks($stock[$i], $stock[$j]));
                }
            }
        } else {
            foreach ($stock as $other) {
                if ($other->id !== $candidate->id) {
                    array_push($outcomes, ...$this->pairChecks($candidate, $other));
                }
            }
        }

        $stats = $findings = [];
        foreach (Check::cases() as $check) {
            if (($this->severity[$check->value] ?? Severity::Off) !== Severity::Off) {
                $stats[$check->value] = [$check, 0, 0];
            }
        }
        foreach ($outcomes as [$check, $hasData, $found]) {
            if (! isset($stats[$check->value])) {
                continue;
            }
            $stats[$check->value][$hasData ? 1 : 2]++;
            if ($found !== null) {
                $findings[] = new Finding($check, $found['severity'] ?? $this->severity[$check->value], $found['ids'], $found['text'], $found['evidence']);
            }
        }
        $rows = array_map(fn (array $s) => new CheckRow($s[0], $s[1], $s[2]), array_values($stats));

        return new CompatibilityReport($tank->size !== null, $findings, $rows);
    }

    /**
     * @return list<array{Check, bool, array{ids: list<string>, text: string, evidence: list<Evidence>, severity: ?Severity}|null}>
     */
    private function tankChecks(FaunaRecord|FloraRecord $r, TankContext $t): array
    {
        $out = [];
        $tankLabel = $t->size?->label() ?? 'brak danych';
        $tankEvidence = fn (string $column, string $value) => new Evidence($column, $value, 'zbiornik');

        $hasCatalogSize = $r->anyTankSize || $r->tankSizes !== [];
        $hasData = $t->size !== null && $hasCatalogSize;
        $smaller = $t->size !== null && ! $r->anyTankSize && $r->tankSizes !== []
            && $t->size->index() < min(array_map(fn ($band) => $band->index(), $r->tankSizes));
        $labels = match (true) {
            $r->anyTankSize => 'Wszystkie',
            $hasCatalogSize => implode('; ', array_map(fn ($band) => $band->label(), $r->tankSizes)),
            default => 'brak danych',
        };
        $sizeEvidence = $hasCatalogSize ? [new Evidence('WielkośćAkwarium', $labels, $r->id)] : [];
        if ($t->size !== null) {
            $sizeEvidence[] = $tankEvidence('Pasmo zbiornika', $tankLabel);
        }
        $sizeNote = $smaller ? ' Wybrane pasmo jest niższe od pasm w katalogu.' : '';
        $out[] = [Check::TankTooSmall, $hasData, $this->found(
            [$r->id], "{$r->name} — wielkość akwarium w katalogu: $labels. Wybrane pasmo zbiornika: $tankLabel.$sizeNote",
            $sizeEvidence, $smaller ? null : Severity::Info,
        )];

        $hasData = $t->type !== null && $r->tankTypes !== [];
        $found = null;
        if ($hasData && ! array_intersect($r->tankTypes, [$t->type, 'Wszystkie'])) {
            $types = implode('; ', $r->tankTypes);
            $found = $this->found([$r->id], "Typy akwarium w katalogu dla {$r->name}: {$types}. Wybrany typ zbiornika: {$t->type}.", [
                new Evidence('TypAkwarium', $types, $r->id), $tankEvidence('Typ zbiornika', $t->type),
            ]);
        }
        $out[] = [Check::TankType, $hasData, $found];

        if ($r instanceof FaunaRecord) {
            $tempMismatch = $this->descriptionMismatch($r, false);
            $hasData = $r->temperature !== null && $t->temperature !== null && $tempMismatch === null;
            $found = null;
            if ($t->temperature !== null && $tempMismatch !== null) {
                $found = $this->found(
                    [$r->id],
                    "Temperatura — brak danych do porównania z ustawieniem zbiornika dla {$r->name}. Źródła w katalogu się różnią: pasmo w tabeli {$tempMismatch['bandLabel']}, opis „{$tempMismatch['excerpt']}”.",
                    [
                        new Evidence('Temperatura', $tempMismatch['tableValue'], $r->id),
                        new Evidence('Opis', $tempMismatch['excerpt'], $r->id),
                    ],
                    Severity::Info,
                );
            } elseif ($tempMismatch === null && $r->temperature !== null && $t->temperature !== null && $r->temperature !== $t->temperature) {
                $catalog = Range::forBand($r->temperature)->label(' °C');
                $tank = Range::forBand($t->temperature)->label(' °C');
                $found = $this->found([$r->id], "Pasmo temperatury w katalogu dla {$r->name}: $catalog. Wybrane pasmo zbiornika: $tank.", [
                    new Evidence('Temperatura', $r->temperature->label(), $r->id), $tankEvidence('Temperatura zbiornika', $t->temperature->label()),
                ]);
            }
            $out[] = [Check::TemperatureTank, $hasData, $found];

            $phMismatch = $this->descriptionMismatch($r, true);
            $hasData = $r->ph !== [] && ! $r->hasUnknownPhBand && $t->ph !== null && $phMismatch === null;
            $found = null;
            if ($t->ph !== null && $phMismatch !== null) {
                $found = $this->found(
                    [$r->id],
                    "pH — brak danych do porównania z ustawieniem zbiornika dla {$r->name}. Źródła w katalogu się różnią: pasmo w tabeli {$phMismatch['bandLabel']}, opis „{$phMismatch['excerpt']}”.",
                    [
                        new Evidence('pH', $phMismatch['tableValue'], $r->id),
                        new Evidence('Opis', $phMismatch['excerpt'], $r->id),
                    ],
                    Severity::Info,
                );
            } elseif ($phMismatch === null && $r->ph !== [] && ! $r->hasUnknownPhBand && $t->ph !== null && ! in_array($t->ph, $r->ph, true)) {
                $values = $this->phLabels($r->ph);
                $found = $this->found([$r->id], "Pasma pH w katalogu dla {$r->name}: {$values}. Wybrane pasmo zbiornika: {$t->ph->label()}.", [
                    new Evidence('pH', $values, $r->id), $tankEvidence('pH zbiornika', $t->ph->label()),
                ]);
            }
            $out[] = [Check::PhTank, $hasData, $found];

            $temperament = $r->temperament?->label();
            $out[] = [Check::Temperament, $temperament !== null, $this->found(
                [$r->id], "{$r->name} — temperament w katalogu: ".($temperament ?? 'brak danych').'.',
                $temperament === null ? [] : [new Evidence('Temperament', $temperament, $r->id)], Severity::Info,
            )];
        } else {
            $found = null;
            if ($r->co2 === Co2Need::Required && ! $t->co2Dosing) {
                $found = $this->found([$r->id], "Baza wskazuje CO2 „{$r->co2->label()}” dla {$r->name}. W sprzęcie zbiornika nie ma pozycji z kategorii „Dozowanie CO2”.", [
                    new Evidence('CO2', $r->co2->label(), $r->id), $tankEvidence('Sprzęt zbiornika', 'brak pozycji z kategorii Dozowanie CO2'),
                ]);
            }
            $out[] = [Check::Co2Missing, $r->co2 !== null, $found];
        }

        return $out;
    }

    /**
     * @return list<array{Check, bool, array{ids: list<string>, text: string, evidence: list<Evidence>, severity: ?Severity}|null}>
     */
    private function pairChecks(FaunaRecord|FloraRecord $a, FaunaRecord|FloraRecord $b): array
    {
        if (! $a instanceof FaunaRecord || ! $b instanceof FaunaRecord) {
            return [];
        }
        $ids = [$a->id, $b->id];
        $out = [];

        $tempMismatchA = $this->descriptionMismatch($a, false);
        $tempMismatchB = $this->descriptionMismatch($b, false);
        if ($tempMismatchA !== null || $tempMismatchB !== null) {
            $out[] = [Check::TemperaturePair, false, $this->pairMismatchFinding($ids, 'Temperatura', $a, $tempMismatchA, $b, $tempMismatchB)];
        } else {
            $hasData = $a->temperature !== null && $b->temperature !== null;
            $found = null;
            if ($a->temperature !== null && $b->temperature !== null && $a->temperature !== $b->temperature) {
                $first = Range::forBand($a->temperature)->label(' °C');
                $second = Range::forBand($b->temperature)->label(' °C');
                $found = $this->found($ids, "Pasma temperatury w katalogu się różnią: {$a->name} ($first), {$b->name} ($second).", [
                    new Evidence('Temperatura', $a->temperature->label(), $a->id), new Evidence('Temperatura', $b->temperature->label(), $b->id),
                ]);
            }
            $out[] = [Check::TemperaturePair, $hasData, $found];
        }

        $phMismatchA = $this->descriptionMismatch($a, true);
        $phMismatchB = $this->descriptionMismatch($b, true);
        if ($phMismatchA !== null || $phMismatchB !== null) {
            $out[] = [Check::PhPair, false, $this->pairMismatchFinding($ids, 'pH', $a, $phMismatchA, $b, $phMismatchB)];
        } else {
            $hasData = $a->ph !== [] && $b->ph !== [] && ! $a->hasUnknownPhBand && ! $b->hasUnknownPhBand;
            $found = null;
            if ($hasData && array_intersect(array_column($a->ph, 'value'), array_column($b->ph, 'value')) === []) {
                $pa = $this->phLabels($a->ph);
                $pb = $this->phLabels($b->ph);
                $found = $this->found($ids, "Pasma pH w katalogu się różnią: {$a->name} ({$pa}), {$b->name} ({$pb}).", [
                    new Evidence('pH', $pa, $a->id), new Evidence('pH', $pb, $b->id),
                ]);
            }
            $out[] = [Check::PhPair, $hasData, $found];
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @param  array{bandLabel: string, tableValue: string, excerpt: string}|null  $mismatchA
     * @param  array{bandLabel: string, tableValue: string, excerpt: string}|null  $mismatchB
     * @return array{ids: list<string>, text: string, evidence: list<Evidence>, severity: ?Severity}
     */
    private function pairMismatchFinding(
        array $ids,
        string $column,
        FaunaRecord $a,
        ?array $mismatchA,
        FaunaRecord $b,
        ?array $mismatchB,
    ): array {
        $details = [];
        $evidence = [];
        foreach ([[$a, $mismatchA], [$b, $mismatchB]] as [$record, $mismatch]) {
            if ($mismatch === null) {
                continue;
            }
            $details[] = "{$record->name} (pasmo w tabeli: {$mismatch['bandLabel']}, opis: „{$mismatch['excerpt']}”)";
            $evidence[] = new Evidence($column, $mismatch['tableValue'], $record->id);
            $evidence[] = new Evidence('Opis', $mismatch['excerpt'], $record->id);
        }

        return $this->found(
            $ids,
            "{$column} — brak danych do porównania ({$a->name} i {$b->name}). Źródła w katalogu się różnią: ".implode('; ', $details).'.',
            $evidence,
            Severity::Info,
        );
    }

    /** @return array{bandLabel: string, tableValue: string, excerpt: string}|null */
    private function descriptionMismatch(FaunaRecord $record, bool $ph): ?array
    {
        if ($ph) {
            if (count($record->ph) !== 1 || $record->hasUnknownPhBand) {
                return null;
            }
            $range = Range::forBand($record->ph[0]);
            $described = DescriptionRange::extract($record->description, true);
            if ($described === null || $range->hasBounds($described[1], $described[2])) {
                return null;
            }

            return [
                'bandLabel' => $range->label(),
                'tableValue' => $this->phLabels($record->ph),
                'excerpt' => $described[0],
            ];
        }

        if ($record->temperature === null) {
            return null;
        }
        $range = Range::forBand($record->temperature);
        $described = DescriptionRange::extract($record->description, false);
        if ($described === null || $range->hasBounds($described[1], $described[2])) {
            return null;
        }

        return [
            'bandLabel' => $range->label(' °C'),
            'tableValue' => $record->temperature->label(),
            'excerpt' => $described[0],
        ];
    }

    /** @param list<PhBand> $bands */
    private function phLabels(array $bands): string
    {
        return implode('; ', array_map(fn (PhBand $b) => $b->label(), $bands));
    }

    /**
     * @param  list<string>  $ids
     * @param  list<Evidence>  $evidence
     * @return array{ids: list<string>, text: string, evidence: list<Evidence>, severity: ?Severity}
     */
    private function found(array $ids, string $text, array $evidence, ?Severity $severity = null): array
    {
        return ['ids' => $ids, 'text' => $text, 'evidence' => $evidence, 'severity' => $severity];
    }
}
