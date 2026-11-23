<?php

use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\WaterHints\ContraindicationGuard;
use App\Domain\Aquarium\Actions\BuildWaterHints;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Actions\SaveRecordSpec;
use App\Domain\KnowledgeBase\Actions\VerifyRecordSpec;
use App\Domain\KnowledgeBase\Data\SaveRecordSpecData;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use App\Livewire\Aquarium\TankMeasurements;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->freezeSecond();
    $this->batch = app(ImportPackage::class)->handle(kbPackage())->batch;
    $this->admin = User::factory()->admin()->create();
    $this->user = aquarist();
    $this->tank = tankFor($this->user);
});

/** @param array<string, mixed> $attributes */
function saveVerifiedSpec(
    User $admin,
    Area $area,
    string $legacyId,
    SpecSourceKind $sourceKind,
    ?string $sourceRef,
    string $quote,
    array $attributes,
    bool $verify = true,
): void {
    $record = Record::query()->where('area', $area->value)->where('legacy_id', $legacyId)->sole();
    $spec = app(SaveRecordSpec::class)->handle($admin, new SaveRecordSpecData(
        recordId: $record->id,
        sourceKind: $sourceKind,
        sourceRef: $sourceRef,
        evidenceQuote: $quote,
        ean: null,
        attributes: $attributes,
    ));
    if ($verify) {
        app(VerifyRecordSpec::class)->handle($admin, $spec);
    }
}

it('compares a KH reading against a verified literature range and shows the explicit source in evidence', function () {
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0001', 'quantity' => 3]);
    saveVerifiedSpec(
        $this->admin,
        Area::Fauna,
        'FN0001',
        SpecSourceKind::Literature,
        'SeriouslyFish — Poecilia reticulata',
        'KH 4–8 °dKH',
        ['kh' => ['min' => 4, 'max' => 8]],
    );

    measure($this->user, $this->tank->id, ['kh' => '12.0']);
    $report = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    $khHint = collect($report->hints)->firstWhere('parameter', 'kh');

    expect($khHint)->not->toBeNull()
        ->and($khHint->message)->toContain('KH 12 °dKH', 'powyżej zakresu', 'Literatura (poza tabelą katalogu)', '4–8 °dKH')
        ->and($khHint->evidence)->toHaveCount(1)
        ->and($khHint->evidence[0]->recordId)->toBe('FN0001')
        ->and($khHint->evidence[0]->source)->toContain('Literatura (poza tabelą katalogu)', 'SeriouslyFish — Poecilia reticulata')
        ->and($khHint->evidence[0]->text())->toContain('Literatura (poza tabelą katalogu)', 'KH 4–8 °dKH', 'FN0001')
        ->and($khHint->productDirection)->toBe('down');

    Livewire::actingAs($this->user)->test(TankMeasurements::class, ['tankId' => $this->tank->id])
        ->assertSee('KH 12 °dKH — powyżej zakresu')
        ->assertSee('Literatura (poza tabelą katalogu)')
        ->assertSee('SeriouslyFish — Poecilia reticulata');
});

it('ignores unverified draft specs in water hints', function () {
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0001', 'quantity' => 3]);
    saveVerifiedSpec(
        $this->admin,
        Area::Fauna,
        'FN0001',
        SpecSourceKind::Literature,
        'SeriouslyFish — Poecilia reticulata',
        'KH 4–8 °dKH',
        ['kh' => ['min' => 4, 'max' => 8]],
        verify: false,
    );

    measure($this->user, $this->tank->id, ['kh' => '12.0']);
    $report = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    $khHint = collect($report->hints)->firstWhere('parameter', 'kh');

    expect($khHint)->not->toBeNull()
        ->and($khHint->message)->toContain('brak danych dla tej obsady')
        ->and($khHint->evidence)->toBe([]);
});

it('recommends a verified care product for a matching deviation and hides it when shrimp contraindication applies', function () {
    saveVerifiedSpec(
        $this->admin,
        Area::Fauna,
        'FN0029',
        SpecSourceKind::Literature,
        'SeriouslyFish — Nannostomus beckfordi',
        'PO4 0,0–0,5 mg/l',
        ['po4' => ['min' => 0.0, 'max' => 0.5]],
    );
    saveVerifiedSpec(
        $this->admin,
        Area::Fauna,
        'FN0151',
        SpecSourceKind::Literature,
        'Karta hodowlana Neocaridina',
        'PO4 0,0–0,5 mg/l',
        ['po4' => ['min' => 0.0, 'max' => 0.5]],
    );
    saveVerifiedSpec(
        $this->admin,
        Area::Care,
        'CH0086',
        SpecSourceKind::ManufacturerLabel,
        'Seachem PhosGuard — etykieta produktu',
        'Usuwa fosforany z wody słodkiej.',
        [
            'parameter' => 'po4',
            'direction' => 'down',
            'water_type' => 'freshwater',
            'contraindications' => [
                ['target' => 'shrimp', 'quote' => 'Ostrożność w zbiornikach z krewetkami.'],
            ],
        ],
    );

    // sama ryba: preparat przechodzi z jawnym źródłem etykiety producenta
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0029', 'quantity' => 5]);
    measure($this->user, $this->tank->id, ['po4' => '1.500']);

    $fishOnly = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    expect($fishOnly->productsByParameter['po4'] ?? null)->not->toBeNull()
        ->and($fishOnly->productsByParameter['po4']->name)->toBe('PhosGuard')
        ->and($fishOnly->productsByParameter['po4']->sourceLabel)->toBe('Etykieta producenta')
        ->and($fishOnly->productsByParameter['po4']->evidence->recordId)->toBe('CH0086')
        ->and($fishOnly->productsByParameter['po4']->evidence->text())->toContain('Etykieta producenta', 'Seachem PhosGuard');

    // po dodaniu krewetki (Typ = Skorupiak): preparat ukryty z wyjaśnieniem
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0151', 'quantity' => 10]);

    $withShrimp = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    expect($withShrimp->productsByParameter['po4'] ?? null)->toBeNull()
        ->and($withShrimp->productBlockReasonsByParameter['po4'] ?? '')->toContain('przeciwwskazanie', 'krewetki', 'skorupiaki');

    Livewire::actingAs($this->user)->test(TankMeasurements::class, ['tankId' => $this->tank->id])
        ->assertDontSee('Opcja z katalogu — PhosGuard')
        ->assertSee('przeciwwskazanie')
        ->assertSee('krewetki');
});

it('hides a contraindicated product with an explanation when stock data is incomplete', function () {
    saveVerifiedSpec(
        $this->admin,
        Area::Fauna,
        'FN0029',
        SpecSourceKind::Literature,
        'SeriouslyFish — Nannostomus beckfordi',
        'PO4 0,0–0,5 mg/l',
        ['po4' => ['min' => 0.0, 'max' => 0.5]],
    );
    saveVerifiedSpec(
        $this->admin,
        Area::Care,
        'CH0086',
        SpecSourceKind::ManufacturerLabel,
        'Seachem PhosGuard — etykieta produktu',
        'Usuwa fosforany z wody słodkiej.',
        [
            'parameter' => 'po4',
            'direction' => 'down',
            'contraindications' => [
                ['target' => 'shrimp', 'quote' => 'Ostrożność przy krewetkach.'],
            ],
        ],
    );

    // przypadek 1: nieznany gatunek fauny spoza katalogu
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0029', 'quantity' => 5]);
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN9999', 'quantity' => 1]);
    measure($this->user, $this->tank->id, ['po4' => '1.500']);

    $withUnknownFauna = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    expect($withUnknownFauna->productsByParameter['po4'] ?? null)->toBeNull()
        ->and($withUnknownFauna->productBlockReasonsByParameter['po4'] ?? '')->toContain('przeciwwskazaniem', 'niepełne dane obsady');

    // przypadek 2: pełna fauna, ale gatunek flory spoza katalogu
    Livestock::query()->where('catalog_id', 'FN9999')->delete();
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Flora, 'catalog_id' => 'FL9999', 'quantity' => 2]);

    $withUnknownFlora = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    expect($withUnknownFlora->productsByParameter['po4'] ?? null)->toBeNull()
        ->and($withUnknownFlora->productBlockReasonsByParameter['po4'] ?? '')->toContain('przeciwwskazanie', 'niepełne');

    // przypadek 3: gatunek fauny bez określonego typu (czysty guard)
    $noKindFauna = new FaunaRecord('FN0999', 'Gatunek testowy', 'Testus sp.', '', null, null, [], [], true, []);
    $reason = (new ContraindicationGuard)->blockReason(
        [['target' => 'snails', 'quote' => 'Nie stosować przy ślimakach.']],
        [$noKindFauna],
        0,
        false,
    );
    expect($reason)->toContain('przeciwwskazanie', 'ślimaki', 'niepełne');
});

it('hides a snail-contraindicated product when a snail is in the tank', function () {
    saveVerifiedSpec(
        $this->admin,
        Area::Care,
        'CH0089',
        SpecSourceKind::ManufacturerLabel,
        'Seachem Acid Buffer — etykieta',
        'Obniża pH w wodzie słodkiej.',
        [
            'parameter' => 'ph',
            'direction' => 'down',
            'contraindications' => [
                ['target' => 'snails', 'quote' => 'Ostrożność przy ślimakach.'],
            ],
        ],
    );
    saveVerifiedSpec(
        $this->admin,
        Area::Fauna,
        'FN0153',
        SpecSourceKind::Literature,
        'Karta ślimaka',
        'pH 6,8–7,5',
        ['ph' => ['min' => 6.8, 'max' => 7.5]],
    );

    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0029', 'quantity' => 3]);
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0153', 'quantity' => 2]);
    measure($this->user, $this->tank->id, ['ph' => '7.9']);

    $report = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    expect($report->product)->toBeNull()
        ->and($report->productBlockReason)->toContain('przeciwwskazanie', 'ślimaki');
});

it('reports a conflict and blocks products when a verified fauna pH spec disagrees with the table band', function () {
    Livestock::create(['tank_id' => $this->tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0029', 'quantity' => 4]);
    saveVerifiedSpec(
        $this->admin,
        Area::Fauna,
        'FN0029',
        SpecSourceKind::Literature,
        'Publikacja testowa',
        'pH 7,0–8,2',
        ['ph' => ['min' => 7.0, 'max' => 8.2]],
    );
    measure($this->user, $this->tank->id, ['ph' => '8.5']);

    $report = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    $phHint = collect($report->hints)->firstWhere('parameter', 'ph');

    expect($report->product)->toBeNull()
        ->and($phHint->message)->toContain('brak danych do porównania', '6,8–7,5', '7–8,2')
        ->and($phHint->evidence)->toHaveCount(2);
});
