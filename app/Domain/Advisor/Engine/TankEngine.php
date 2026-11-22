<?php

namespace App\Domain\Advisor\Engine;

/** Ranking Tank „dowódca i awans”: wagi 4/3/2/1 dostają kolejno podane kryteria (bez temperatury pH awansuje na 2). */
final class TankEngine
{
    private const WEIGHTS = [4, 3, 2, 1];

    /** Ryba o co najmniej tyle pasm większa od zbiornika odpada. */
    private const MAX_FISH_GAP = 3;

    /** @param list<FaunaRecord> $records */
    public function rank(array $records, TankProfile $profile): Ranking
    {
        $fish = $profile->fish ?? FishBand::matching($profile->size);
        $criteria = array_values(array_filter(
            [Criterion::Size, Criterion::Fish, $profile->temperature ? Criterion::Temperature : null, $profile->ph ? Criterion::Ph : null],
        ));
        $weights = array_combine(array_map(fn (Criterion $c) => $c->value, $criteria), array_slice(self::WEIGHTS, 0, count($criteria)));

        $ranked = [];
        $excluded = $noData = 0;
        foreach ($records as $record) {
            $typeMismatch = $profile->type !== null
                && ! array_intersect($record->tankTypes, [$profile->type, 'Wszystkie']);
            $tooBig = $record->size !== null && $record->size->index() - $profile->size->index() >= self::MAX_FISH_GAP;
            if ($typeMismatch || $tooBig) {
                $excluded++;

                continue;
            }

            $scores = array_filter([
                Criterion::Size->value => $this->sizeScore($record, $profile->size),
                Criterion::Fish->value => $this->ordinal($record->size, $fish),
                Criterion::Temperature->value => $profile->temperature ? $this->ordinal($record->temperature, $profile->temperature) : null,
                Criterion::Ph->value => $profile->ph ? $this->phScore($record, $profile->ph) : null,
            ], fn (?int $score) => $score !== null);

            $weight = array_sum(array_intersect_key($weights, $scores));
            if ($weight === 0) {
                $noData++;

                continue;
            }
            $points = array_sum(array_map(fn (int $score, string $key) => $score * $weights[$key], $scores, array_keys($scores)));
            $ranked[] = new ScoredRecord($record, $points, 3 * $weight, $scores);
        }

        usort($ranked, fn (ScoredRecord $a, ScoredRecord $b) => $this->compare($a, $b));

        $topCount = 0;
        foreach ($ranked as $candidate) {
            if ($candidate->percent() !== $ranked[0]->percent() || $candidate->scores !== $ranked[0]->scores) {
                break;
            }
            $topCount++;
        }
        $decidedBy = null;
        if ($topCount === 1 && count($ranked) > 1 && $ranked[1]->percent() === $ranked[0]->percent()) {
            $decidedBy = $this->firstDifference($ranked[0], $ranked[1]);
        }

        return new Ranking($ranked, max($topCount, 1), $decidedBy, $excluded, $noData, $fish, $profile->fish === null);
    }

    /** Najlepsza ocena z pasm rekordu (v2: wielowartościowe pasmo akwarium); „Wszystkie” daje 3. */
    private function sizeScore(FaunaRecord $record, TankBand $tank): ?int
    {
        if ($record->anyTankSize) {
            return 3;
        }

        $scores = array_map(fn (TankBand $band) => match ($tank->index() - $band->index()) {
            0 => 3,
            1 => 2,
            -1 => 1,
            default => 0,
        }, $record->tankSizes);

        return $scores ? max($scores) : null;
    }

    private function ordinal(FishBand|TemperatureBand|PhBand|null $value, FishBand|TemperatureBand|PhBand $wanted): ?int
    {
        return $value === null ? null : match (abs($value->index() - $wanted->index())) {
            0 => 3,
            1 => 1,
            default => 0,
        };
    }

    private function phScore(FaunaRecord $record, PhBand $wanted): ?int
    {
        $scores = array_map(fn (PhBand $band) => $this->ordinal($band, $wanted), $record->ph);

        return $scores ? max($scores) : null;
    }

    private function compare(ScoredRecord $a, ScoredRecord $b): int
    {
        $keys = [$b->percent() <=> $a->percent()];
        foreach (Criterion::cases() as $criterion) {
            $keys[] = ($b->scores[$criterion->value] ?? -1) <=> ($a->scores[$criterion->value] ?? -1);
        }
        $keys[] = strcasecmp($a->record->latinName, $b->record->latinName);
        $keys[] = strcmp($a->record->id, $b->record->id);

        return array_find($keys, fn (int $key) => $key !== 0) ?? 0;
    }

    private function firstDifference(ScoredRecord $a, ScoredRecord $b): ?Criterion
    {
        return array_find(Criterion::cases(), fn (Criterion $c) => ($a->scores[$c->value] ?? -1) !== ($b->scores[$c->value] ?? -1));
    }
}
