<?php

namespace App\Domain\Advisor\Engine\Compatibility;

use App\Domain\Advisor\Engine\Co2Need;
use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\FloraRecord;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\Temperament;

/**
 * Czysty silnik zgodności obsady (projekt nowych funkcji, §2). Każde ustalenie niesie dowód z rekordu bazy;
 * kontrola bez danych liczy się jako „brak danych”, nie jako zgodna. Bez kandydata sprawdza całą obsadę,
 * z kandydatem tylko to, co dotyczy kandydata.
 */
final class CompatibilityEngine
{
    /** Odstęp pasm (temperatury, pH), od którego zakresy nie mają części wspólnej. */
    private const MIN_GAP = 2;

    /** Ryba o co najmniej tyle pasm większa od zbiornika (ta sama reguła co w rankingu Tank). */
    private const MAX_FISH_GAP = 3;

    /**
     * @param  array<string, Severity>  $severity  ważność po kluczu kontroli; brak klucza = wyłączona
     * @param  list<array{aggressor: Temperament, other: Temperament}>  $temperaments  pary ryba - ryba dające uwagę
     */
    public function __construct(private array $severity, private array $temperaments) {}

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
        foreach ($outcomes as [$check, $hasData, $found]) {
            $severity = $this->severity[$check->value] ?? Severity::Off;
            if ($severity === Severity::Off) {
                continue;
            }
            $stats[$check->value] ??= [$check, 0, 0];
            $stats[$check->value][$hasData ? 1 : 2]++;
            if ($found !== null) {
                $findings[] = new Finding($check, $severity, $found['ids'], $found['text'], $found['evidence']);
            }
        }
        $rows = array_map(fn (array $s) => new CheckRow($s[0], $s[1], $s[2]), array_values($stats));
        $order = fn (CheckRow $row) => array_search($row->check, Check::cases(), true);
        usort($rows, fn (CheckRow $a, CheckRow $b) => $order($a) <=> $order($b));

        return new CompatibilityReport($tank->size !== null, $findings, $rows);
    }

    /**
     * @return list<array{Check, bool, array{ids: list<string>, text: string, evidence: list<Evidence>}|null}>
     */
    private function tankChecks(FaunaRecord|FloraRecord $r, TankContext $t): array
    {
        $out = [];
        $tankLabel = (string) $t->size?->label();
        $tankEvidence = fn (string $column, string $value) => new Evidence($column, $value, 'zbiornik');

        // zbiornik za mały według bazy
        $hasData = $t->size !== null && ($r->anyTankSize || $r->tankSizes !== []);
        $found = null;
        if ($hasData && ! $r->anyTankSize && $t->size->index() < min(array_map(fn ($b) => $b->index(), $r->tankSizes))) {
            $labels = implode('; ', array_map(fn ($b) => $b->label(), $r->tankSizes));
            $found = $this->found([$r->id], "Baza wskazuje dla {$r->name} zbiornik: {$labels}. Ten zbiornik: {$tankLabel}.", [
                new Evidence('WielkośćAkwarium', $labels, $r->id), $tankEvidence('Pasmo zbiornika', $tankLabel),
            ]);
        }
        $out[] = [Check::TankTooSmall, $hasData, $found];

        // dorosła ryba za duża na zbiornik
        if ($r instanceof FaunaRecord && $r->kind === 'Ryba') {
            $hasData = $r->size !== null && $t->size !== null;
            $found = null;
            if ($hasData && $r->size->index() - $t->size->index() >= self::MAX_FISH_GAP) {
                $found = $this->found([$r->id], "Baza wskazuje dla {$r->name} wielkość: {$r->size->label()}. To co najmniej ".self::MAX_FISH_GAP." pasma więcej niż pasmo zbiornika ({$tankLabel}).", [
                    new Evidence('Wielkość', $r->size->label(), $r->id), $tankEvidence('Pasmo zbiornika', $tankLabel),
                ]);
            }
            $out[] = [Check::FishTooLarge, $hasData, $found];
        }

        // typ zbiornika
        $hasData = $t->type !== null && $r->tankTypes !== [];
        $found = null;
        if ($hasData && ! array_intersect($r->tankTypes, [$t->type, 'Wszystkie'])) {
            $types = implode('; ', $r->tankTypes);
            $found = $this->found([$r->id], "Baza wskazuje dla {$r->name} typy akwarium: {$types}. Ten zbiornik: {$t->type}.", [
                new Evidence('TypAkwarium', $types, $r->id), $tankEvidence('Typ zbiornika', $t->type),
            ]);
        }
        $out[] = [Check::TankType, $hasData, $found];

        if ($r instanceof FaunaRecord) {
            $hasData = $r->temperature !== null && $t->temperature !== null;
            $found = null;
            if ($hasData && abs($r->temperature->index() - $t->temperature->index()) >= self::MIN_GAP) {
                $found = $this->found([$r->id], "Baza wskazuje dla {$r->name} temperaturę: {$r->temperature->label()}. Zbiornik ma ustawioną: {$t->temperature->label()}.", [
                    new Evidence('Temperatura', $r->temperature->label(), $r->id), $tankEvidence('Temperatura zbiornika', $t->temperature->label()),
                ]);
            }
            $out[] = [Check::TemperatureTank, $hasData, $found];

            $hasData = $r->ph !== [] && $t->ph !== null;
            $found = null;
            if ($hasData && $this->phGap($r->ph, [$t->ph]) >= self::MIN_GAP) {
                $values = $this->phLabels($r->ph);
                $found = $this->found([$r->id], "Baza wskazuje dla {$r->name} pH: {$values}. Zbiornik ma ustawione: {$t->ph->label()}.", [
                    new Evidence('pH', $values, $r->id), $tankEvidence('pH zbiornika', $t->ph->label()),
                ]);
            }
            $out[] = [Check::PhTank, $hasData, $found];
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
     * @return list<array{Check, bool, array{ids: list<string>, text: string, evidence: list<Evidence>}|null}>
     */
    private function pairChecks(FaunaRecord|FloraRecord $a, FaunaRecord|FloraRecord $b): array
    {
        if (! $a instanceof FaunaRecord || ! $b instanceof FaunaRecord) {
            return [];
        }
        $ids = [$a->id, $b->id];
        $out = [];

        $hasData = $a->temperature !== null && $b->temperature !== null;
        $found = null;
        if ($hasData && abs($a->temperature->index() - $b->temperature->index()) >= self::MIN_GAP) {
            $found = $this->found($ids, "Baza wskazuje rozłączne zakresy temperatury: {$a->name} ({$a->temperature->label()}) i {$b->name} ({$b->temperature->label()}).", [
                new Evidence('Temperatura', $a->temperature->label(), $a->id), new Evidence('Temperatura', $b->temperature->label(), $b->id),
            ]);
        }
        $out[] = [Check::TemperaturePair, $hasData, $found];

        $hasData = $a->ph !== [] && $b->ph !== [];
        $found = null;
        if ($hasData && $this->phGap($a->ph, $b->ph) >= self::MIN_GAP) {
            $pa = $this->phLabels($a->ph);
            $pb = $this->phLabels($b->ph);
            $found = $this->found($ids, "Baza wskazuje rozłączne zakresy pH: {$a->name} ({$pa}) i {$b->name} ({$pb}).", [
                new Evidence('pH', $pa, $a->id), new Evidence('pH', $pb, $b->id),
            ]);
        }
        $out[] = [Check::PhPair, $hasData, $found];

        if ($a->kind === 'Ryba' && $b->kind === 'Ryba') {
            $hasData = $a->temperament !== null && $b->temperament !== null;
            $found = null;
            foreach ($hasData ? [[$a, $b], [$b, $a]] : [] as [$x, $y]) {
                foreach ($this->temperaments as $pair) {
                    if ($x->temperament === $pair['aggressor'] && $y->temperament === $pair['other']) {
                        $found = $this->found($ids, "Baza wskazuje temperament: {$x->name} — {$x->temperament->label()}, {$y->name} — {$y->temperament->label()}.", [
                            new Evidence('Temperament', $x->temperament->label(), $x->id), new Evidence('Temperament', $y->temperament->label(), $y->id),
                        ]);
                        break 2;
                    }
                }
            }
            $out[] = [Check::Temperament, $hasData, $found];
        }

        return $out;
    }

    /**
     * Najmniejszy odstęp pasm pH między dwiema listami.
     *
     * @param  list<PhBand>  $a
     * @param  list<PhBand>  $b
     */
    private function phGap(array $a, array $b): int
    {
        return min(array_merge(...array_map(fn (PhBand $x) => array_map(fn (PhBand $y) => abs($x->index() - $y->index()), $b), $a)));
    }

    /** @param list<PhBand> $bands */
    private function phLabels(array $bands): string
    {
        return implode('; ', array_map(fn (PhBand $b) => $b->label(), $bands));
    }

    /**
     * @param  list<string>  $ids
     * @param  list<Evidence>  $evidence
     * @return array{ids: list<string>, text: string, evidence: list<Evidence>}
     */
    private function found(array $ids, string $text, array $evidence): array
    {
        return ['ids' => $ids, 'text' => $text, 'evidence' => $evidence];
    }
}
