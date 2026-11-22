<?php

namespace App\Domain\Aquarium;

use Illuminate\Support\Facades\Validator;

/** Walidacja wejścia modułu z polskimi komunikatami (aplikacja nie ma plików tłumaczeń). */
final class Validation
{
    private const MESSAGES = [
        'required' => 'Pole „:attribute” jest wymagane.',
        'string' => 'Pole „:attribute” musi być tekstem.',
        'numeric' => 'Pole „:attribute” musi być liczbą.',
        'integer' => 'Pole „:attribute” musi być liczbą całkowitą.',
        'decimal' => 'Pole „:attribute” może mieć najwyżej :decimal miejsc po przecinku.',
        'date' => 'Pole „:attribute” musi być poprawną datą.',
        'date_format' => 'Pole „:attribute” ma niepoprawny format.',
        'in' => 'Wybierz poprawną wartość w polu „:attribute”.',
        'enum' => 'Wybierz poprawną wartość w polu „:attribute”.',
        'min' => ['numeric' => 'Pole „:attribute” musi być nie mniejsze niż :min.'],
        'max' => ['numeric' => 'Pole „:attribute” musi być nie większe niż :max.', 'string' => 'Pole „:attribute” może mieć najwyżej :max znaków.'],
        'between' => ['numeric' => 'Pole „:attribute” musi mieścić się w przedziale :min–:max.'],
        'gt' => ['numeric' => 'Pole „:attribute” musi być większe niż :value.'],
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>
     */
    public static function validate(array $data, array $rules, array $attributes = []): array
    {
        return Validator::make($data, $rules, self::MESSAGES, $attributes)->validate();
    }
}
