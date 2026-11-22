<?php

namespace App\Domain\Advisor\Engine;

/**
 * Krótki dobór gościa: ścieżka Fauna → Ryba → Ogólne / Towarzyskie, strefa, temperament i region nieokreślone.
 * Odwzorowuje kolejne zawężanie puli ze starego silnika: odpowiedź o temperaturze lub pH jest użyta tylko wtedy,
 * gdy w bieżącej puli jest rekord z tym pasmem, a po niej pula traci rekordy z oceną 0.
 * Ocena bez wag: 3/2/1/0 za odległość pasma, procent z liczby ocenionych kryteriów.
 */
final class DropEngine
{
    public const KIND = 'Ryba';

    public const TANK_TYPE = 'Ogólne / Towarzyskie';

    private const MAX_FISH_GAP = 3;

    /** Wynik dogrywki technicznej bez różnic (suma, trafienia 3, trafienia 1, dopasowanie akwarium, typ). */
    private const NO_DIFFERENCE = [0, 0, 0, 0, 0];

    /** @param list<FaunaRecord> $records */
    public function rank(array $records, TankProfile $profile): Ranking
    {
        $pool = array_values(array_filter($records, fn (FaunaRecord $r) => $this->inScope($r, $profile)));
        if ($pool === []) {
            return new Ranking([]);
        }

        $available = array_filter(FishBand::cases(), fn (FishBand $band) => array_filter($pool, fn (FaunaRecord $r) => $r->size === $band) !== []);
        $target = $profile->fish ?? FishBand::matching($profile->size);
        $fish = in_array($target, $available, true) ? $target : $this->nearest(array_values($available), $target);

        $answers = [Criterion::Fish->value => $fish, Criterion::Temperature->value => $profile->temperature, Criterion::Ph->value => $profile->ph];
        foreach ([Criterion::Temperature, Criterion::Ph] as $criterion) {
            $answer = $answers[$criterion->value];
            if ($answer !== null && array_filter($pool, fn (FaunaRecord $r) => in_array($answer, $this->bands($r, $criterion), true)) !== []) {
                $pool = array_values(array_filter($pool, fn (FaunaRecord $r) => $this->distance($this->bands($r, $criterion), $answer) !== 0));
            } else {
                $answers[$criterion->value] = null;
            }
        }
        $answers = array_filter($answers);

        $rows = [];
        foreach ($pool as $record) {
            $scores = [];
            foreach ($answers as $key => $answer) {
                $scores[$key] = $this->distance($this->bands($record, Criterion::from($key)), $answer);
            }
            $rows[] = [
                new ScoredRecord($record, array_sum($scores), 3 * count($scores), $scores),
                [$this->tankFit($record, $profile->size), $this->typeFit($record, $profile->type)],
            ];
        }
        usort($rows, fn (array $a, array $b) => $this->compare($a, $b));

        [$topCount, $decidedBy] = $this->outcome($rows, count($answers));

        return new Ranking(array_column($rows, 0), $topCount, $decidedBy, 0, 0, $fish, $profile->fish === null, $profile->fish !== null && $fish !== $profile->fish);
    }

    private function inScope(FaunaRecord $record, TankProfile $profile): bool
    {
        $tank = $profile->size->index();
        $fitsTank = $record->anyTankSize
            || array_filter($record->tankSizes, fn (TankBand $band) => $band->index() <= $tank) !== [];

        return $record->kind === self::KIND
            && ($record->size === null || $record->size->index() - $tank < self::MAX_FISH_GAP)
            && $fitsTank
            && ($profile->type === null || in_array($profile->type, $record->tankTypes, true));
    }

    /** @return list<FishBand|TemperatureBand|PhBand> */
    private function bands(FaunaRecord $record, Criterion $criterion): array
    {
        return match ($criterion) {
            Criterion::Fish => array_filter([$record->size]),
            Criterion::Temperature => array_filter([$record->temperature]),
            default => $record->ph,
        };
    }

    /** @param list<FishBand|TemperatureBand|PhBand> $values */
    private function distance(array $values, FishBand|TemperatureBand|PhBand $answer): int
    {
        $scores = array_map(fn ($value) => match (abs($value->index() - $answer->index())) {
            0 => 3,
            1 => 2,
            2 => 1,
            default => 0,
        }, $values);

        return $scores ? max($scores) : 0;
    }

    /**
     * Najbliższe dostępne pasmo; przy równej odległości mniejsze.
     *
     * @param  list<FishBand>  $available
     */
    private function nearest(array $available, FishBand $target): FishBand
    {
        $best = $available[0];
        foreach ($available as $band) {
            if (abs($band->index() - $target->index()) < abs($best->index() - $target->index())) {
                $best = $band;
            }
        }

        return $best;
    }

    private function tankFit(FaunaRecord $record, TankBand $tank): int
    {
        if (in_array($tank, $record->tankSizes, true)) {
            return match (count($record->tankSizes)) {
                1 => 3,
                2 => 2,
                default => 1,
            };
        }

        return $record->anyTankSize ? 1 : 0;
    }

    private function typeFit(FaunaRecord $record, ?string $type): int
    {
        if ($type === null || ! in_array($type, $record->tankTypes, true)) {
            return 0;
        }

        return match (count($record->tankTypes)) {
            1 => 3,
            2 => 2,
            default => 1,
        };
    }

    /**
     * @param  array{ScoredRecord, array{int, int}}  $a
     * @param  array{ScoredRecord, array{int, int}}  $b
     */
    private function compare(array $a, array $b): int
    {
        $keys = $this->compareVisible($a[0], $b[0]);
        foreach (Criterion::cases() as $criterion) {
            $keys[] = ($b[0]->scores[$criterion->value] ?? -1) <=> ($a[0]->scores[$criterion->value] ?? -1);
        }
        $keys = [...$keys, ...$this->compareBackend($a[1], $b[1]), strcmp($a[0]->record->id, $b[0]->record->id)];

        return array_find($keys, fn (int $key) => $key !== 0) ?? 0;
    }

    /** @return list<int> suma, trafienia 3, trafienia 1 (mniej lepiej) */
    private function compareVisible(ScoredRecord $a, ScoredRecord $b): array
    {
        return [
            $b->points <=> $a->points,
            $this->hits($b->scores, 3) <=> $this->hits($a->scores, 3),
            $this->hits($a->scores, 1) <=> $this->hits($b->scores, 1),
        ];
    }

    /**
     * @param  array{int, int}  $a
     * @param  array{int, int}  $b
     * @return list<int>
     */
    private function compareBackend(array $a, array $b): array
    {
        return [
            array_sum($b) <=> array_sum($a),
            $this->hits($b, 3) <=> $this->hits($a, 3),
            $this->hits($a, 1) <=> $this->hits($b, 1),
            $b[0] <=> $a[0],
            $b[1] <=> $a[1],
        ];
    }

    /** @param array<int|string, int> $scores */
    private function hits(array $scores, int $value): int
    {
        return count(array_filter($scores, fn (int $score) => $score === $value));
    }

    /**
     * Grupa równorzędnych na szczycie. Przy 1–2 kryteriach liczy się tylko wynik widoczny, przy większej liczbie
     * także dogrywka techniczna (dopasowanie akwarium i typu), która może wskazać jednego zwycięzcę.
     *
     * @param  list<array{ScoredRecord, array{int, int}}>  $rows
     * @return array{int, ?Criterion}
     */
    private function outcome(array $rows, int $criteria): array
    {
        if (count($rows) < 2) {
            return [1, null];
        }

        [$leader, $runner] = [$rows[0][0], $rows[1][0]];
        $sameVisible = fn (ScoredRecord $row) => $this->compareVisible($leader, $row) === [0, 0, 0] && $leader->scores === $row->scores;

        if (! $sameVisible($runner)) {
            $decidedBy = $leader->points === $runner->points
                ? array_find(Criterion::cases(), fn (Criterion $c) => ($leader->scores[$c->value] ?? -1) !== ($runner->scores[$c->value] ?? -1))
                : null;

            return [1, $decidedBy];
        }
        if ($criteria > 2 && $this->compareBackend($rows[0][1], $rows[1][1]) !== self::NO_DIFFERENCE) {
            return [1, null];
        }

        $count = 0;
        foreach ($rows as [$row, $backend]) {
            if (! $sameVisible($row) || ($criteria > 2 && $this->compareBackend($rows[0][1], $backend) !== self::NO_DIFFERENCE)) {
                break;
            }
            $count++;
        }

        return [$count, null];
    }
}
