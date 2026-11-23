<?php

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\WaterHints\WaterHintsEngine;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Contracts\Catalog;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

it('compares the reference catalogue and explains the result', function (array $ids, array $readings, array $messages, bool $reviewStock) {
    $batch = app(Catalog::class)->activeBatchId();
    $stock = array_map(fn ($id) => new StockLine(GuideArea::Fauna, $id), $ids);
    [$fauna, $unknown] = app(Compatibility::class)->waterRequirements($stock, $batch);
    $key = $readings['ph'] === null ? 'temp_c' : 'ph';
    [$hint] = (new WaterHintsEngine)->assess($readings, $fauna, $unknown, [$key], $batch);

    expect($hint->message)->toContain(...$messages)->and($hint->reviewStock)->toBe($reviewStock);
    foreach ($hint->evidence as $proof) {
        expect($proof->batch)->toBe($batch)->and($ids)->toContain($proof->recordId);
    }
})->with([
    'gupik pH 7.9' => [['FN0001'], ['ph' => 7.9, 'temp_c' => null], ['brak danych', 'Źródła w katalogu się różnią — sprawdź u sprzedawcy', '6,8–7,5', 'pH 7,0–8,5'], false],
    'gupik pH 7.5' => [['FN0001'], ['ph' => 7.5, 'temp_c' => null], ['brak danych', 'pH 7,0–8,5'], false],
    'gupik 20°C' => [['FN0001'], ['ph' => null, 'temp_c' => 20.0], ['poniżej zakresu podanego w katalogu', '22–25,5 °C'], false],
    'gupik 21.8°C' => [['FN0001'], ['ph' => null, 'temp_c' => 21.8], ['odczyt między pasmami katalogu', '22–25,5 °C'], false],
    'gupik 25.8°C' => [['FN0001'], ['ph' => null, 'temp_c' => 25.8], ['odczyt między pasmami katalogu', '22–25,5 °C'], false],
    'błyszczek 20°C' => [['FN0165'], ['ph' => null, 'temp_c' => 20.0], ['brak danych', 'poniżej 18 °C', '15–22°C'], false],
    'krewetka pH 7.9' => [['FN0150'], ['ph' => 7.9, 'temp_c' => null], ['powyżej zakresu podanego w katalogu', '6,8–7,5'], false],
    'krewetka pH 6.5' => [['FN0150'], ['ph' => 6.5, 'temp_c' => null], ['poniżej zakresu podanego w katalogu', '6,8–7,5'], false],
    'gupik i bocja' => [['FN0001', 'FN0117'], ['ph' => null, 'temp_c' => 23.0], ['pasma w katalogu nie mają części wspólnej', '22–25,5 °C', '26–30 °C'], true],
    'niepełna obsada' => [['FN0150', 'FN9999'], ['ph' => 7.9, 'temp_c' => null], ['brak danych katalogowych dla części obsady'], false],
]);
