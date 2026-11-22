<?php

namespace App\Domain\Advisor\Engine;

/**
 * Punktacja, sortowanie i grupy wyniku dla puli jednego obszaru.
 * Punkty 3/2/1/0 za kryterium, procent = suma / (3 × liczba aktywnych kryteriów). Kolejność: suma, więcej ocen 3, mniej ocen 1,
 * pierwsze różne kryterium planu, dogrywka techniczna (dopasowanie litrażu i typu akwarium), na końcu ID.
 */
final class Ranker
{
    private const TIE_PREFIX = 'Kilka rekordów uzyskało ten sam wynik punktowy. Lider wygrał ';

    private const RULES = ['Wielkość akwarium', 'Typ akwarium'];

    /**
     * @param  list<string>  $criteria  kolumny aktywnych kryteriów w kolejności planu
     * @param  array<string, string>  $answers
     */
    public function __construct(
        private Rules $rules,
        private GuideArea $area,
        private array $criteria,
        private array $answers,
        private ?string $volume,
        private ?string $type,
    ) {}

    /** @param list<Item> $pool */
    public function rank(array $pool, string $label): Result
    {
        $columns = Vocabulary::columns($this->area);
        $names = array_map(fn (string $c) => $columns[$c]['label'], $this->criteria);
        if ($pool === []) {
            return new Result($this->area, $label, GroupType::Empty, [], [], 0, [], 0, $names, 'Brak dopasowań dla wybranych warunków. Zacznij od nowa i zmień odpowiedzi.', false);
        }

        $scored = array_map($this->evaluate(...), $pool);
        usort($scored, $this->compare(...));
        [$type, $tie, $notice] = $this->resolve($scored);

        $insufficient = $this->criteria === [];
        $limit = $this->rules->cards;
        if ($this->rules->includeTies && ! $insufficient && in_array($type, [GroupType::PartialTie, GroupType::FullTie], true)) {
            $limit = max($limit, $tie);
        }
        $cards = [];
        foreach (array_slice($scored, 0, max($this->rules->shortlist, $limit)) as $i => $row) {
            $role = $i < $tie && $tie > 1 ? CardRole::Tie : ($i === 0 ? CardRole::Main : CardRole::Alternative);
            $cards[] = new Card($role, $this->area, $row->item->id, $row->item->name, $row->percent, $row->total, 3 * count($this->criteria), $row->scores, $row->reasons, $row->warnings, $this->fit($row->percent));
        }

        return new Result(
            $this->area, $label, $type, array_slice($cards, 0, $limit), $cards, count($scored),
            array_map(fn (Scored $r) => $r->percent, array_slice($scored, $limit, 3)), $tie, $names,
            $insufficient ? 'Za mało danych do porównania: żadna odpowiedź nie różnicuje wyników, procenty nie mają znaczenia.' : $notice,
            $insufficient,
        );
    }

    private function evaluate(Item $item): Scored
    {
        $columns = Vocabulary::columns($this->area);
        $scores = $reasons = $warnings = [];
        $total = $ideal = $weak = 0;
        foreach ($this->criteria as $column) {
            $score = Scoring::column($this->area, $column, $this->answers[$column], $item);
            $scores[$columns[$column]['key']] = $score;
            $total += $score;
            $reason = Scoring::reason($column, $columns[$column]['label'], $score, $item->raw($column));
            if ($score === 3) {
                $ideal++;
                $reasons[] = $reason;
            } elseif ($score === 2) {
                // Region, marka i producent: ocena 2 znaczy „inny”, to ostrzeżenie, nie powód.
                if (in_array($column, ['Region', 'Marka', 'producent'], true)) {
                    $warnings[] = $reason;
                } else {
                    $reasons[] = $reason;
                }
            } elseif ($score === 1) {
                $weak++;
                $warnings[] = $reason;
            }
        }
        $percent = $this->criteria === [] ? 100 : (int) round(100 * $total / (3 * count($this->criteria)));

        return new Scored($item, $total, $ideal, $weak, $scores, array_slice($reasons, 0, 4), array_slice($warnings, 0, 3), $percent, [$this->tankFit($item), $this->typeFit($item)]);
    }

    private function fit(int $percent): string
    {
        [$low, $mid, $high] = $this->rules->fit;

        return match (true) {
            $percent >= $high => 'Dopasowanie bardzo wysokie',
            $percent >= $mid => 'Dopasowanie wysokie',
            $percent >= $low => 'Dopasowanie warunkowe',
            default => 'Dopasowanie słabe',
        };
    }

    private function tankFit(Item $item): int
    {
        $sizes = $item->tokens('WielkośćAkwarium');
        if ($this->volume === null || $sizes === []) {
            return 0;
        }
        if (in_array($this->volume, $sizes, true)) {
            return match (count(array_diff($sizes, [Vocabulary::WILDCARD]))) {
                0, 1 => 3,
                2 => 2,
                default => 1,
            };
        }

        return in_array(Vocabulary::WILDCARD, $sizes, true) ? 1 : 0;
    }

    private function typeFit(Item $item): int
    {
        $types = $item->tokens('TypAkwarium');
        if ($this->type === null || ! in_array($this->type, $types, true)) {
            return 0;
        }

        return match (count($types)) {
            1 => 3,
            2 => 2,
            default => 1,
        };
    }

    private function compare(Scored $a, Scored $b): int
    {
        $keys = [$b->total <=> $a->total, $this->hits($b->scores, 3) <=> $this->hits($a->scores, 3), $this->hits($a->scores, 1) <=> $this->hits($b->scores, 1)];
        foreach ($this->criteria as $column) {
            $key = Vocabulary::columns($this->area)[$column]['key'];
            $keys[] = $b->scores[$key] <=> $a->scores[$key];
        }
        $keys = [...$keys, ...$this->backendKeys($a, $b), strcmp($a->item->id, $b->item->id)];

        return array_find($keys, fn (int $key) => $key !== 0) ?? 0;
    }

    /** @return list<int> suma, oceny 3, oceny 1 (mniej lepiej), litraż, typ */
    private function backendKeys(Scored $a, Scored $b): array
    {
        return [
            array_sum($b->backend) <=> array_sum($a->backend),
            $this->hits($b->backend, 3) <=> $this->hits($a->backend, 3),
            $this->hits($a->backend, 1) <=> $this->hits($b->backend, 1),
            $b->backend[0] <=> $a->backend[0],
            $b->backend[1] <=> $a->backend[1],
        ];
    }

    /** @param array<int|string, int> $scores */
    private function hits(array $scores, int $value): int
    {
        return count(array_filter($scores, fn (int $score) => $score === $value));
    }

    private function sameVisible(Scored $a, Scored $b): bool
    {
        return $a->total === $b->total && $a->ideal === $b->ideal && $a->weak === $b->weak && $a->scores === $b->scores;
    }

    private function sameBackend(Scored $a, Scored $b): bool
    {
        return array_filter($this->backendKeys($a, $b)) === [];
    }

    /**
     * Typ grupy, liczba równorzędnych na szczycie i opis. Przy 1–2 kryteriach remis liczy się bez dogrywki technicznej
     * (zamiast sztucznego lidera pokazujemy ex aequo), przy większej liczbie dogrywka może wskazać lidera.
     *
     * @param  list<Scored>  $scored  posortowane
     * @return array{GroupType, int, ?string}
     */
    private function resolve(array $scored): array
    {
        if (count($scored) < 2) {
            return [GroupType::Leader, 1, null];
        }
        [$a, $b] = $scored;
        $columns = Vocabulary::columns($this->area);
        if ($a->total !== $b->total) {
            return [GroupType::Leader, 1, null];
        }
        if ($a->ideal !== $b->ideal) {
            return [GroupType::Tiebreak, 1, self::TIE_PREFIX.'większą liczbą dopasowań idealnych.'];
        }
        if ($a->weak !== $b->weak) {
            return [GroupType::Tiebreak, 1, self::TIE_PREFIX.'mniejszą liczbą dopasowań słabych.'];
        }
        foreach ($this->criteria as $column) {
            $key = $columns[$column]['key'];
            if ($a->scores[$key] !== $b->scores[$key]) {
                return [GroupType::Tiebreak, 1, self::TIE_PREFIX."na wcześniejszym kryterium: {$columns[$column]['label']}."];
            }
        }

        $small = count($this->criteria) <= 2;
        if (! $small && ($notice = $this->backendNotice($a, $b)) !== '') {
            return [GroupType::Tiebreak, 1, $notice];
        }

        $tie = 0;
        foreach ($scored as $row) {
            if (! $this->sameVisible($a, $row) || (! $small && ! $this->sameBackend($a, $row))) {
                break;
            }
            $tie++;
        }
        $partial = $tie < count($scored);

        return [
            $partial ? GroupType::PartialTie : GroupType::FullTie,
            $tie,
            match (true) {
                $small && $partial => "Na górze jest $tie równie dobrych propozycji. Przy tak małej liczbie odpowiedzi Doradca nie wskazuje jednego zwycięzcy.",
                $small => 'Rekordy pozostały nierozróżnialne na aktywnych kryteriach. Przy 1–2 aktywnych kryteriach pokazujemy ex aequo zamiast sztucznego lidera.',
                $partial => "Na górze jest $tie równie dobrych propozycji. Kolejne są niżej, od najlepszej.",
                default => 'Rekordy uzyskały identyczny wynik na wszystkich aktywnych kryteriach oraz w dogrywce remisu. Kolejność techniczna według ID.',
            },
        ];
    }

    /** Opis wygranej lidera w dogrywce technicznej (pusty, gdy dogrywka nie rozstrzyga). */
    private function backendNotice(Scored $a, Scored $b): string
    {
        $rule = array_find_key($a->backend, fn (int $score, int $i) => $score !== $b->backend[$i]);
        if (array_sum($a->backend) !== array_sum($b->backend)) {
            return self::TIE_PREFIX.($rule === null ? 'wyższą punktacją w dodatkowym doprecyzowaniu.'
                : "dodatkowym doprecyzowaniem na kryterium: {$this->ruleName($rule)} ({$a->backend[$rule]} vs {$b->backend[$rule]}).");
        }
        if ($this->hits($a->backend, 3) !== $this->hits($b->backend, 3)) {
            return self::TIE_PREFIX.'większą liczbą idealnych dopasowań.';
        }
        if ($this->hits($a->backend, 1) !== $this->hits($b->backend, 1)) {
            return self::TIE_PREFIX.'mniejszą liczbą słabych dopasowań.';
        }

        return $rule === null ? '' : self::TIE_PREFIX."na wcześniejszym kryterium dogrywki: {$this->ruleName($rule)}.";
    }

    private function ruleName(int $rule): string
    {
        return self::RULES[$rule];
    }
}
