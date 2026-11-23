<?php

namespace App\Domain\Advisor\Engine;

/**
 * Weryfikacja wybranego rekordu: pytania o warunki zbiornika, zgodność każdej odpowiedzi (3/2/1/0), werdykt z procentu,
 * a poniżej progu także lepsza alternatywa z tej samej klasy i co najwyżej jedna korekta (pH, temperatura, CO2).
 */
final class Verifier
{
    public function __construct(private Records $records, private Rules $rules) {}

    /** @param array<string, string> $answers kolumna => etykieta */
    public function question(Item $record, array $answers): ?Question
    {
        foreach (Vocabulary::verifyPlan($record->area) as $column) {
            $options = $this->options($record, $column);
            if (isset($answers[$column]) || count($options) <= 1) {
                continue;
            }
            [$key, $text] = Vocabulary::VERIFY_QUESTIONS[$column];
            $optimal = implode('; ', $record->tokens($column));

            return new Question(
                "verify.$key", $optimal === '' ? $text : "$text Optymalnie dla tego rekordu: $optimal.",
                Vocabulary::options($options), 42, "Weryfikacja rekordu — {$record->name}",
                $answers === [] ? 'Teraz potwierdzamy warunki dla wybranego rekordu.' : null,
            );
        }

        return null;
    }

    /** @return list<string> */
    private function options(Item $record, string $column): array
    {
        return match ($column) {
            'WielkośćAkwarium' => $this->records->tankSizes(),
            'TypAkwarium' => $this->records->types([$record->area === GuideArea::Care ? GuideArea::Fauna : $record->area]),
            'pH' => Vocabulary::order(Records::distinct($this->records->of(GuideArea::Fauna), 'pH'), Vocabulary::scale('pH')),
            'Temperatura' => Vocabulary::order(Records::distinct($this->records->of(GuideArea::Fauna), 'Temperatura'), Vocabulary::scale('Temperatura')),
            default => Vocabulary::order(Records::distinct($this->records->of(GuideArea::Flora), 'CO2'), Vocabulary::scale('CO2')),
        };
    }

    /** @param array<string, string> $answers */
    public function verify(Item $record, array $answers): Verification
    {
        $eval = $this->evaluate($record, $answers);
        [$low, $high] = $this->rules->verification;
        $alternative = $correction = null;
        if ($eval['percent'] < $low) {
            $alternative = $this->alternative($record, $answers, $high);
            $correction = $this->correction($record, $answers, $eval['mismatches']);
        }

        return new Verification(
            $record->area, $record->id, $record->name, $eval['percent'],
            $eval['percent'] >= $high ? 'Zgodny' : ($eval['percent'] >= $low ? 'Zgodny warunkowo' : 'Słabe dopasowanie'),
            $eval['compatibility'], $eval['reasons'], $eval['warnings'], $alternative, $correction, $this->supporting($record),
            $answers === [] ? 'Za mało danych do porównania: brak odpowiedzi do sprawdzenia, procent nie ma znaczenia.' : null,
        );
    }

    /**
     * @param  array<string, string>  $answers
     * @return array{percent: int, points: int, max: int, compatibility: list<array{label: string, user: string, optimal: string, status: string}>, reasons: list<string>, warnings: list<string>, mismatches: list<string>}
     */
    private function evaluate(Item $record, array $answers): array
    {
        $compat = $reasons = $warnings = $mismatches = [];
        $points = $max = 0;
        foreach ($answers as $column => $answer) {
            [$score, $status, $note] = $this->score($record, $column, $answer);
            $max += 3;
            $points += $score;
            $compat[] = ['label' => $this->label($column), 'user' => $answer, 'optimal' => $record->raw($column), 'status' => $status];
            if ($score >= 2) {
                $reasons[] = $note;
            } else {
                $warnings[] = $note;
                $mismatches[] = $column;
            }
        }

        return [
            'percent' => $max > 0 ? (int) round(100 * $points / $max) : 100, 'points' => $points, 'max' => $max,
            'compatibility' => $compat, 'reasons' => $reasons, 'warnings' => $warnings, 'mismatches' => $mismatches,
        ];
    }

    private function label(string $column): string
    {
        return match ($column) {
            'WielkośćAkwarium' => 'Wielkość akwarium',
            'TypAkwarium' => 'Typ akwarium',
            default => $column,
        };
    }

    /** @return array{int, string, string} ocena, status, opis */
    private function score(Item $record, string $column, string $answer): array
    {
        $values = $record->tokens($column);
        $label = $this->label($column);

        if ($column === 'WielkośćAkwarium') {
            if (in_array(Vocabulary::WILDCARD, $values, true)) {
                return [3, 'zgodne', 'Ten rekord jest dopuszczony dla pełnego zakresu wielkości akwarium.'];
            }
            if (in_array($answer, $values, true)) {
                return [3, 'zgodne', 'Litraż zbiornika jest zgodny z profilem optymalnym rekordu.'];
            }
            $wanted = TankBand::fromLabel($answer)?->index();
            $gaps = array_filter(array_map(fn (string $v) => ($i = TankBand::fromLabel($v)?->index()) === null || $wanted === null ? null : abs($i - $wanted), $values), fn ($g) => $g !== null);

            return $gaps !== [] && min($gaps) === 1
                ? [1, 'wymaga uwagi', 'Litraż jest bliski zakresowi optymalnemu, ale nie mieści się idealnie.']
                : [0, 'niezgodne', 'Litraż odbiega od optymalnego zakresu dla tego rekordu.'];
        }

        if ($column === 'TypAkwarium') {
            return in_array($answer, $values, true)
                ? [3, 'zgodne', 'Typ akwarium pokrywa się z profilem optymalnym rekordu.']
                : [0, 'niezgodne', 'Typ akwarium nie odpowiada profilowi tego rekordu.'];
        }

        // pH, temperatura, CO2: dokładne 3, sąsiednie 2, reszta 0.
        $best = $values === [] ? 0 : max(array_map(fn (string $v) => ($s = Scoring::ordinal($answer, $v, Vocabulary::scale($column))) === 1 ? 0 : $s, $values));

        return match ($best) {
            3 => [3, 'zgodne', "$label jest zgodne z optimum tego rekordu."],
            2 => [2, 'częściowo zgodne', "$label jest blisko optimum, ale nie idealnie."],
            default => [0, 'niezgodne', "$label odbiega od optimum tego rekordu."],
        };
    }

    /** @param array<string, string> $answers */
    private function alternative(Item $record, array $answers, int $threshold): ?Card
    {
        $best = null;
        $bestEval = null;
        foreach ($this->records->of($record->area) as $candidate) {
            if ($candidate->id === $record->id || ! $this->sameClass($record, $candidate)) {
                continue;
            }
            $eval = $this->evaluate($candidate, $answers);
            if ($eval['percent'] >= $threshold && ($bestEval === null || $eval['percent'] > $bestEval['percent'])) {
                [$best, $bestEval] = [$candidate, $eval];
            }
        }

        return $best === null || $bestEval === null ? null : new Card(
            CardRole::Better, $best->area, $best->id, $best->name, $bestEval['percent'], $bestEval['points'], $bestEval['max'], [],
            ['Ten rekord lepiej wpisuje się w aktualne warunki zbiornika.', ...array_slice($bestEval['reasons'], 0, 3)], [],
        );
    }

    private function sameClass(Item $a, Item $b): bool
    {
        $same = fn (string $column) => $a->raw($column) !== '' && $a->raw($column) === $b->raw($column);

        return match ($a->area) {
            GuideArea::Fauna => $same('Typ'),
            GuideArea::Flora => $same('Strefa') || $same('Wielkość'),
            GuideArea::Equipment => $same('kategoria_glowna') && $same('rodzaj'),
            GuideArea::Care => $same('kategoria_zadania') && $same('rodzaj_zadania') && ($a->raw('obszar') === '' || $a->raw('obszar') === $b->raw('obszar')),
        };
    }

    /**
     * Jedna korekta, pierwsza niezgodność w kolejności pH, temperatura, CO2. Karta zachowuje obszar własnego rekordu.
     *
     * @param  array<string, string>  $answers
     * @param  list<string>  $mismatches
     */
    private function correction(Item $record, array $answers, array $mismatches): ?Card
    {
        $rules = $this->rules->corrections;
        $item = match (true) {
            in_array('pH', $mismatches, true) => $this->care(
                str_contains($record->raw('pH'), 'kwaśna') ? $rules['ph_acid'] : $rules['ph_other'],
                ['CH0041', 'CH0090', 'CH0094', 'CH0099'],
            ),
            in_array('Temperatura', $mismatches, true) => $this->heating($answers['WielkośćAkwarium'] ?? '', $rules['heating_category']),
            in_array('CO2', $mismatches, true) => $this->care($rules['co2']),
            default => null,
        };

        return $item === null ? null : new Card(
            CardRole::Correction, $item->area, $item->id, $item->name, $rules['percent'], 0, 0, [],
            [$item->area === GuideArea::Care ? 'Ten produkt pomaga skorygować najsłabszy parametr wybranego rekordu.' : 'To rozwiązanie pomaga doprowadzić warunki zbiornika bliżej optimum wybranego rekordu.'], [],
        );
    }

    /**
     * Pierwszy produkt pielęgnacji zawierający jedną z fraz (kolejność fraz ważniejsza niż kolejność rekordów).
     *
     * @param  list<string>  $terms
     * @param  list<string>  $excludedIds
     */
    private function care(array $terms, array $excludedIds = []): ?Item
    {
        foreach ($terms as $term) {
            $found = array_find($this->records->of(GuideArea::Care), fn (Item $i) => ! in_array($i->id, $excludedIds, true) && str_contains(
                mb_strtolower(implode(' ', array_filter([$i->raw('produkt'), $i->raw('obszar'), $i->raw('rodzaj_zadania'), $i->raw('opis')]))),
                mb_strtolower($term),
            ));
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function heating(string $volume, string $category): ?Item
    {
        return array_find($this->records->of(GuideArea::Equipment), function (Item $i) use ($volume, $category) {
            $sizes = $i->tokens('WielkośćAkwarium');

            return $i->raw('kategoria_glowna') === $category
                && ($volume === '' || $sizes === [] || in_array($volume, $sizes, true) || in_array(Vocabulary::WILDCARD, $sizes, true));
        });
    }

    /**
     * Do dwóch produktów współistniejących: pokarmy dla ryb lub krewetek (fauna), nawozy i preparaty (flora).
     *
     * @return list<Ref>
     */
    private function supporting(Item $record): array
    {
        $uses = match ($record->area) {
            GuideArea::Fauna => [$record->raw('Typ') === 'Skorupiak' ? 'Pokarmy dla krewetek' : 'Pokarmy dla ryb'],
            GuideArea::Flora => ['Nawozy', 'Preparaty wzbogacające'],
            default => [],
        };
        $items = array_filter($this->records->of(GuideArea::Care), fn (Item $i) => in_array($i->raw('obszar'), $uses, true));

        return array_map(fn (Item $i) => new Ref($i->area, $i->id, $i->name), array_slice(array_values($uses === [] ? [] : $items), 0, 2));
    }
}
