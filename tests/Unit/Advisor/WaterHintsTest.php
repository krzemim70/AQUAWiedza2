<?php

use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Advisor\Engine\WaterHints\PhProductCheck;
use App\Domain\Advisor\Engine\WaterHints\WaterHintsEngine;

function waterFauna(?string $description = null, array $ph = [PhBand::Neutral], ?TemperatureBand $temperature = TemperatureBand::Warm): FaunaRecord
{
    return new FaunaRecord('FN0000', 'Gatunek', '', 'Ryba', null, $temperature, $ph, [], false, [], description: $description);
}

it('compares the absolute pH boundary without extending the catalogue band', function (?float $reading, string $outcome) {
    [$hint] = (new WaterHintsEngine)->assess(['ph' => $reading, 'temp_c' => null], [waterFauna()], 0, ['ph'], 12);

    expect($hint->message)->toContain($outcome);
    if ($reading !== null) {
        expect($hint->evidence[0]->batch)->toBe(12);
    }
})->with([
    [6.79, 'poniżej zakresu podanego w katalogu'],
    [6.8, 'w zakresie podanym w katalogu'],
    [7.5, 'w zakresie podanym w katalogu'],
    [7.51, 'powyżej zakresu podanego w katalogu'],
    [null, 'brak odczytu'],
]);

it('does not decide from several different ranges in a description or from unrelated numbers', function () {
    $record = waterFauna('Twardość 6–10°dGH. W handlu: pH 6,5–7,0. W naturze: pH 7,2–8,0.');
    [$hint] = (new WaterHintsEngine)->assess(['ph' => 7.9, 'temp_c' => null], [$record], 0, ['ph']);

    expect($hint->message)->toBe('pH 7,9 — powyżej zakresu podanego w katalogu dla: Gatunek (6,8–7,5).')
        ->and($hint->evidence)->toHaveCount(1);
});

it('keeps both sources when the description gives a different pH range at the end of a sentence', function () {
    $record = waterFauna('Źródło podaje twardość 8–30°dGH oraz pH 7,0–8,5.');
    [$hint] = (new WaterHintsEngine)->assess(['ph' => 7.9, 'temp_c' => null], [$record], 0, ['ph']);

    expect($hint->message)->toContain('brak danych', '6,8–7,5', 'pH 7,0–8,5')
        ->and($hint->evidence)->toHaveCount(2);
});

it('uses the intersection of the catalogue bands and preserves exclusive limits', function () {
    $stock = [waterFauna(ph: [PhBand::Acidic, PhBand::Neutral]), waterFauna(ph: [PhBand::Neutral, PhBand::Alkaline])];
    [$hint] = (new WaterHintsEngine)->assess(['ph' => 7.9, 'temp_c' => null], $stock, 0, ['ph']);

    expect($hint->message)->toContain('powyżej zakresu podanego w katalogu', '(6,8–7,5)')
        ->and($hint->evidence)->toHaveCount(2);
});

it('gives missing data for an incomplete stock instead of comparing the whole stock', function () {
    [$hint] = (new WaterHintsEngine)->assess(['ph' => 7.9, 'temp_c' => null], [waterFauna()], 1, ['ph']);

    expect($hint->message)->toBe('pH 7,9 — brak danych katalogowych dla części obsady.')
        ->and($hint->evidence)->toHaveCount(1);
});

it('requires an unambiguous affirmative pH range for a product', function (string $description) {
    [$hint] = (new WaterHintsEngine)->assess(['ph' => 7.9, 'temp_c' => null], [waterFauna($description)], 0, ['ph']);

    expect($hint->phProduct)->toBe(PhProductCheck::UnconfirmedRanges);
})->with([
    'Wymaga stabilnego pH.',
    'Nie zaleca się pH 6,8–7,5.',
    'W handlu: pH 6,8–7,5. W naturze: pH mniej więcej 6,0–8,0.',
    'Zapis zakresu pH 6,8–7,5–8,0.',
    'pH 6,8–7,5 lub 7,9.',
]);
