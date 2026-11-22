<?php

namespace App\Domain\Advisor\Engine;

/** Ocena rekordu względem odpowiedzi na jedno kryterium: 3/2/1/0. Wartości wielokrotne rekordu: liczy się najlepsza. */
final class Scoring
{
    public static function column(GuideArea $area, string $column, string $answer, Item $item): int
    {
        $values = $item->tokens($column);
        $spec = Vocabulary::columns($area)[$column];

        return $values === [] ? 0 : max(array_map(fn (string $value) => match ($spec['score']) {
            'ordinal' => self::ordinal($answer, $value, $spec['order']),
            'binary' => $answer === $value ? 3 : 2,
            default => $answer === $value ? 3 : 0,
        }, $values));
    }

    /**
     * Odległość na skali: 3/2/1/0 za 0/1/2/większą; wartość spoza skali daje 0.
     *
     * @param  list<string>  $scale
     */
    public static function ordinal(string $answer, string $value, array $scale): int
    {
        $a = array_search($answer, $scale, true);
        $v = array_search($value, $scale, true);

        return $a === false || $v === false ? 0 : match (abs($a - $v)) {
            0 => 3,
            1 => 2,
            2 => 1,
            default => 0,
        };
    }

    /** Uzasadnienie oceny 3/2/1 (dla regionu, marki i producenta ocena 2 znaczy „inny”). */
    public static function reason(string $column, string $label, int $score, string $value): string
    {
        if ($column === 'Region') {
            return "$label: ".($score === 3 ? 'zgodny region' : 'inny region')." ($value).";
        }
        if (in_array($column, ['Marka', 'producent'], true)) {
            return "$label: ".($score === 3 ? 'zgodna' : 'inna')." ($value).";
        }

        return "$label: ".match ($score) {
            3 => 'pełna zgodność',
            2 => 'zgodność częściowa',
            default => 'zgodność słaba',
        }." ($value).";
    }
}
