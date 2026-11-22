<?php

namespace App\Domain\Aquarium\Data;

/** Wynik kalkulatora: wartość (null = brak czego liczyć), jednostka, kroki „Jak policzono”, uwagi i dodatkowe liczby. */
final readonly class CalculatorResult
{
    /**
     * @param  list<string>  $steps
     * @param  list<string>  $notes
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public ?float $value,
        public string $unit,
        public array $steps = [],
        public array $notes = [],
        public array $details = [],
    ) {}

    /** Liczba po polsku (przecinek); bez miejsc po przecinku podanych jawnie obcina zbędne zera. */
    public static function fmt(float $n, ?int $decimals = null): string
    {
        if ($decimals !== null) {
            return number_format($n, $decimals, ',', '');
        }

        return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    }
}
