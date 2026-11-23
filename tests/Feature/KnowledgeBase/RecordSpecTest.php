<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Actions\SaveRecordSpec;
use App\Domain\KnowledgeBase\Actions\VerifyRecordSpec;
use App\Domain\KnowledgeBase\Contracts\StructuredSpecs;
use App\Domain\KnowledgeBase\Data\SaveRecordSpecData;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\KnowledgeBase\Models\RecordSpec;
use App\Domain\KnowledgeBase\Models\RecordSpecStatus;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use App\Domain\Settings\SettingKey;
use App\Filament\Resources\RecordSpecs\Pages\CreateRecordSpec;
use App\Filament\Resources\RecordSpecs\Pages\EditRecordSpec;
use App\Filament\Resources\RecordSpecs\Pages\ListRecordSpecs;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    $this->admin = User::factory()->admin()->create();
});

function specRecord(Area $area, string $legacyId): Record
{
    return Record::query()->where('area', $area->value)->where('legacy_id', $legacyId)->firstOrFail();
}

function specEditPackage(string $dir, string $file, string $search, string $replace): void
{
    $path = $dir.DIRECTORY_SEPARATOR.$file;
    file_put_contents($path, str_replace($search, $replace, (string) file_get_contents($path)));
}

it('saves a draft without exposing it in StructuredSpecs until verified, and resets to draft on edit', function () {
    $care = specRecord(Area::Care, 'CH0089');
    $quote = 'Acid Buffer pozwala bezpiecznie obniżać pH w połączeniu z Alkaline Buffer, przy regularnej kontroli testami.';
    $specs = app(StructuredSpecs::class);

    $draft = app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $care->id,
        sourceKind: SpecSourceKind::CatalogQuote,
        sourceRef: null,
        evidenceQuote: $quote,
        ean: '0000116032605',
        attributes: ['parameter' => 'ph', 'direction' => 'down'],
    ));

    expect($draft->status)->toBe(RecordSpecStatus::Draft)
        ->and($draft->verified_by)->toBeNull()
        ->and($draft->verified_at)->toBeNull()
        ->and($specs->find(Area::Care, 'CH0089'))->toBeNull()
        ->and($specs->forRecords(Area::Care, ['CH0089']))->toBe([])
        ->and($specs->forArea(Area::Care))->toBe([]);

    $verified = app(VerifyRecordSpec::class)->handle($this->admin, $draft);

    expect($verified->status)->toBe(RecordSpecStatus::Verified)
        ->and($verified->verified_by)->toBe($this->admin->id)
        ->and($verified->verified_at)->not->toBeNull();

    $dto = $specs->find(Area::Care, 'CH0089');
    expect($dto)->not->toBeNull()
        ->and($dto->area)->toBe(Area::Care)
        ->and($dto->id)->toBe('CH0089')
        ->and($dto->sourceKind)->toBe(SpecSourceKind::CatalogQuote)
        ->and($dto->evidenceQuote)->toBe($quote)
        ->and($dto->ean)->toBe('0000116032605')
        ->and($dto->attributes)->toEqualCanonicalizing(['parameter' => 'ph', 'direction' => 'down'])
        ->and($specs->forRecords(Area::Care, ['CH0089']))->toHaveKey('CH0089')
        ->and($specs->forArea(Area::Care))->toHaveKey('CH0089');

    // ponowny zapis cofa status do szkicu i usuwa wpis z kontraktu do czasu weryfikacji
    $edited = app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $care->id,
        sourceKind: SpecSourceKind::ManufacturerLabel,
        sourceRef: 'Seachem Acid Buffer — karta produktu',
        evidenceQuote: 'Dawkowanie: 2 g na 80 l.',
        ean: '0000116032605',
        attributes: [
            'parameter' => 'ph',
            'direction' => 'down',
            'dose_amount' => 2,
            'unit' => 'g',
            'dose_per_liters' => 80,
            'package_amount' => 300,
            'water_type' => 'freshwater',
            'contraindications' => [
                ['target' => 'shrimp', 'quote' => 'Unikać gwałtownych skoków pH przy krewetkach.'],
            ],
        ],
    ));

    expect($edited->status)->toBe(RecordSpecStatus::Draft)
        ->and($edited->verified_at)->toBeNull()
        ->and($specs->find(Area::Care, 'CH0089'))->toBeNull();

    app(VerifyRecordSpec::class)->handle($this->admin, $edited);
    $verifiedLabel = $specs->find(Area::Care, 'CH0089');
    expect($verifiedLabel)->not->toBeNull()
        ->and($verifiedLabel->sourceKind)->toBe(SpecSourceKind::ManufacturerLabel)
        ->and($verifiedLabel->contraindications())->toBe([
            ['target' => 'shrimp', 'quote' => 'Unikać gwałtownych skoków pH przy krewetkach.'],
        ]);

    expect(Activity::query()->where('log_name', 'catalog_record_specs')->where('subject_id', $draft->id)->pluck('event')->all())
        ->toBe(['created', 'verified', 'updated', 'verified']);
});

it('rejects a catalog_quote that does not literally appear in the active description', function () {
    $care = specRecord(Area::Care, 'CH0089');

    expect(fn () => app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $care->id,
        sourceKind: SpecSourceKind::CatalogQuote,
        sourceRef: null,
        evidenceQuote: 'Cytat spoza aktywnego opisu rekordu.',
        ean: null,
        attributes: ['parameter' => 'ph', 'direction' => 'down'],
    )))->toThrow(ValidationException::class);

    // przeciwwskazanie z cytatem spoza opisu przy źródle catalog_quote też jest odrzucane
    expect(fn () => app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $care->id,
        sourceKind: SpecSourceKind::CatalogQuote,
        sourceRef: null,
        evidenceQuote: 'pozwala bezpiecznie obniżać pH',
        ean: null,
        attributes: [
            'parameter' => 'ph',
            'direction' => 'down',
            'contraindications' => [
                ['target' => 'shrimp', 'quote' => 'Niebezpieczne dla krewetek'],
            ],
        ],
    )))->toThrow(ValidationException::class);

    expect(RecordSpec::count())->toBe(0);
});

it('enforces source kind rules by area, source_ref requirement and settings', function () {
    $fauna = specRecord(Area::Fauna, 'FN0001');
    $care = specRecord(Area::Care, 'CH0089');
    $equipment = specRecord(Area::Equipment, 'SP0001');

    // etykieta producenta niedozwolona dla fauny
    expect(fn () => app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $fauna->id,
        sourceKind: SpecSourceKind::ManufacturerLabel,
        sourceRef: 'Karta',
        evidenceQuote: 'pH 7,0–8,5',
        ean: null,
        attributes: ['ph' => ['min' => 7.0, 'max' => 8.5]],
    )))->toThrow(ValidationException::class);

    // literatura niedozwolona dla preparatów
    expect(fn () => app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $care->id,
        sourceKind: SpecSourceKind::Literature,
        sourceRef: 'Publikacja',
        evidenceQuote: 'Obniża pH',
        ean: null,
        attributes: ['parameter' => 'ph', 'direction' => 'down'],
    )))->toThrow(ValidationException::class);

    // literatura bez source_ref odrzucona
    expect(fn () => app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $fauna->id,
        sourceKind: SpecSourceKind::Literature,
        sourceRef: null,
        evidenceQuote: 'pH 7,0–8,5',
        ean: null,
        attributes: ['ph' => ['min' => 7.0, 'max' => 8.5]],
    )))->toThrow(ValidationException::class);

    // sprzęt akceptuje etykietę producenta
    $eqSpec = app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $equipment->id,
        sourceKind: SpecSourceKind::ManufacturerLabel,
        sourceRef: 'Aquael Ultramax 1000 — instrukcja',
        evidenceQuote: 'Moc 15 W, wydajność 1000 l/h.',
        ean: '5905546321891',
        attributes: ['power_w' => 15, 'flow_l_h' => 1000, 'water_type' => 'freshwater'],
    ));
    expect($eqSpec->status)->toBe(RecordSpecStatus::Draft);

    // wyłączenie źródła w ustawieniach blokuje zapis
    shopSetting(SettingKey::CatalogSpecSources, ['catalog_quote']);
    expect(fn () => app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $fauna->id,
        sourceKind: SpecSourceKind::Literature,
        sourceRef: 'SeriouslyFish',
        evidenceQuote: 'pH 7,0–8,5',
        ean: null,
        attributes: ['ph' => ['min' => 7.0, 'max' => 8.5]],
    )))->toThrow(ValidationException::class);
});

it('disables a catalog_quote spec and logs audit reason when the quote disappears in a new batch', function () {
    $care = specRecord(Area::Care, 'CH0089');
    $fauna = specRecord(Area::Fauna, 'FN0001');
    $otherFauna = specRecord(Area::Fauna, 'FN0002');
    $specs = app(StructuredSpecs::class);

    $careSpec = app(VerifyRecordSpec::class)->handle($this->admin, app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $care->id,
        sourceKind: SpecSourceKind::CatalogQuote,
        sourceRef: null,
        evidenceQuote: 'pozwala bezpiecznie obniżać pH',
        ean: null,
        attributes: ['parameter' => 'ph', 'direction' => 'down'],
    )));

    $faunaQuoteSpec = app(VerifyRecordSpec::class)->handle($this->admin, app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $fauna->id,
        sourceKind: SpecSourceKind::CatalogQuote,
        sourceRef: null,
        evidenceQuote: 'SeriouslyFish podaje twardość około 8–30°dGH oraz pH 7,0–8,5.',
        ean: null,
        attributes: ['ph' => ['min' => 7.0, 'max' => 8.5], 'gh' => ['min' => 8, 'max' => 30]],
    )));

    $literatureSpec = app(VerifyRecordSpec::class)->handle($this->admin, app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $otherFauna->id,
        sourceKind: SpecSourceKind::Literature,
        sourceRef: 'SeriouslyFish — Poecilia wingei',
        evidenceQuote: 'pH 7,0–8,5, twardość 15–35°dGH',
        ean: null,
        attributes: ['ph' => ['min' => 7.0, 'max' => 8.5], 'gh' => ['min' => 15, 'max' => 35]],
    )));

    // import nowej partii ze zmienionym opisem CH0089 i FN0001
    $dir = kbPackage();
    specEditPackage($dir, 'baza_pielegnacja_end1.txt.php', 'pozwala bezpiecznie obniżać pH', 'zmieniony opis preparatu');
    specEditPackage($dir, 'opisy_fauna.json.php', 'SeriouslyFish podaje twardość około 8–30°dGH oraz pH 7,0–8,5.', 'Zmieniony fragment opisu.');
    $import = app(ImportPackage::class)->handle($dir, $this->admin);

    expect($careSpec->refresh()->status)->toBe(RecordSpecStatus::Disabled)
        ->and($careSpec->disabled_reason)->toContain('Cytat')
        ->and($careSpec->disabled_at)->not->toBeNull()
        ->and($faunaQuoteSpec->refresh()->status)->toBe(RecordSpecStatus::Disabled)
        ->and($literatureSpec->refresh()->status)->toBe(RecordSpecStatus::Verified)
        ->and($specs->find(Area::Care, 'CH0089'))->toBeNull()
        ->and($specs->find(Area::Fauna, 'FN0001'))->toBeNull()
        ->and($specs->find(Area::Fauna, 'FN0002')?->range('gh'))->toBe(['min' => 15.0, 'max' => 35.0]);

    $audit = Activity::query()
        ->where('log_name', 'catalog_record_specs')
        ->where('event', 'disabled')
        ->where('subject_id', $careSpec->id)
        ->sole();

    expect($audit->getProperty('batch_id'))->toBe($import->batch->id)
        ->and($audit->getProperty('record'))->toBe('CH0089')
        ->and($audit->getProperty('reason'))->not->toBeEmpty();

    // ponowna weryfikacja wyłączonego wpisu bez poprawy cytatu jest odrzucana
    expect(fn () => app(VerifyRecordSpec::class)->handle($this->admin, $careSpec))
        ->toThrow(ValidationException::class);
    expect($careSpec->refresh()->status)->toBe(RecordSpecStatus::Disabled);
});

it('forbids users without CatalogSpecEdit from saving, verifying or opening the editorial panel', function () {
    $care = specRecord(Area::Care, 'CH0089');
    $user = aquarist(fishroom: true);
    $suspendedAdmin = User::factory()->admin()->create(['suspended_at' => now()]);

    $dto = new SaveRecordSpecData(
        recordId: $care->id,
        sourceKind: SpecSourceKind::CatalogQuote,
        sourceRef: null,
        evidenceQuote: 'pozwala bezpiecznie obniżać pH',
        ean: null,
        attributes: ['parameter' => 'ph', 'direction' => 'down'],
    );

    expect(fn () => app(SaveRecordSpec::class)->handle($user, $dto))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveRecordSpec::class)->handle($suspendedAdmin, $dto))->toThrow(AuthorizationException::class);

    $spec = app(SaveRecordSpec::class)->handle($this->admin, $dto);
    expect(fn () => app(VerifyRecordSpec::class)->handle($user, $spec))->toThrow(AuthorizationException::class)
        ->and(fn () => app(VerifyRecordSpec::class)->handle($suspendedAdmin, $spec))->toThrow(AuthorizationException::class);

    Filament::setCurrentPanel('admin');
    $this->actingAs($user, 'admin');

    Livewire::test(ListRecordSpecs::class)->assertForbidden();
    Livewire::test(CreateRecordSpec::class)->assertForbidden();
    Livewire::test(EditRecordSpec::class, ['record' => $spec->getKey()])->assertForbidden();
});

it('filters specs by status, creates drafts and verifies them in the admin editorial panel', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin, 'admin');

    $care = specRecord(Area::Care, 'CH0089');
    $fauna = specRecord(Area::Fauna, 'FN0001');
    $otherFauna = specRecord(Area::Fauna, 'FN0002');

    // tworzenie szkicu preparatu w panelu
    Livewire::test(CreateRecordSpec::class)
        ->set('data.record_id', $care->id)
        ->fillForm([
            'record_id' => $care->id,
            'source_kind' => SpecSourceKind::CatalogQuote->value,
            'evidence_quote' => 'pozwala bezpiecznie obniżać pH',
            'attributes' => [
                'parameter' => 'ph',
                'direction' => 'down',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $draftSpec = RecordSpec::query()->where('record_id', $care->id)->sole();
    expect($draftSpec->status)->toBe(RecordSpecStatus::Draft);

    // odrzucenie złego cytatu w formularzu
    Livewire::test(CreateRecordSpec::class)
        ->set('data.record_id', $fauna->id)
        ->fillForm([
            'record_id' => $fauna->id,
            'source_kind' => SpecSourceKind::CatalogQuote->value,
            'evidence_quote' => 'Nieistniejący cytat w opisie gupika.',
            'attributes' => [
                'ph' => ['min' => '7.0', 'max' => '8.5'],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['evidence_quote']);

    // przygotowanie wpisów w trzech stanach do filtra listy
    $verifiedSpec = app(VerifyRecordSpec::class)->handle($this->admin, app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $fauna->id,
        sourceKind: SpecSourceKind::Literature,
        sourceRef: 'SeriouslyFish — Poecilia reticulata',
        evidenceQuote: 'pH 7,0–8,5',
        ean: null,
        attributes: ['ph' => ['min' => 7.0, 'max' => 8.5]],
    )));

    $disabledSpec = app(SaveRecordSpec::class)->handle($this->admin, new SaveRecordSpecData(
        recordId: $otherFauna->id,
        sourceKind: SpecSourceKind::CatalogQuote,
        sourceRef: null,
        evidenceQuote: 'pH 7,0–8,5',
        ean: null,
        attributes: ['ph' => ['min' => 7.0, 'max' => 8.5]],
    ));
    $disabledSpec->update([
        'status' => RecordSpecStatus::Disabled,
        'disabled_reason' => 'Cytat nie występuje już w aktywnym opisie rekordu.',
        'disabled_at' => now(),
    ]);

    Livewire::test(ListRecordSpecs::class)
        ->assertCanSeeTableRecords([$draftSpec, $verifiedSpec, $disabledSpec])
        ->filterTable('status', RecordSpecStatus::Draft->value)
        ->assertCanSeeTableRecords([$draftSpec])
        ->assertCanNotSeeTableRecords([$verifiedSpec, $disabledSpec])
        ->filterTable('status', RecordSpecStatus::Verified->value)
        ->assertCanSeeTableRecords([$verifiedSpec])
        ->assertCanNotSeeTableRecords([$draftSpec, $disabledSpec])
        ->filterTable('status', RecordSpecStatus::Disabled->value)
        ->assertCanSeeTableRecords([$disabledSpec])
        ->assertCanNotSeeTableRecords([$draftSpec, $verifiedSpec])
        ->resetTableFilters()
        ->assertTableActionVisible('verify', $draftSpec)
        ->assertTableActionHidden('verify', $verifiedSpec)
        ->callTableAction('verify', $draftSpec);

    expect($draftSpec->refresh()->status)->toBe(RecordSpecStatus::Verified)
        ->and(app(StructuredSpecs::class)->find(Area::Care, 'CH0089'))->not->toBeNull();

    // edycja w panelu cofa do szkicu, a akcja nagłówka ponownie weryfikuje
    Livewire::test(EditRecordSpec::class, ['record' => $verifiedSpec->getKey()])
        ->fillForm([
            'source_kind' => SpecSourceKind::Literature->value,
            'source_ref' => 'SeriouslyFish — Poecilia reticulata (aktualizacja)',
            'evidence_quote' => 'pH 7,0–8,5; GH 8–30',
            'attributes' => [
                'ph' => ['min' => '7.0', 'max' => '8.5'],
                'gh' => ['min' => '8', 'max' => '30'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->callAction('verify')
        ->assertHasNoActionErrors();

    expect($verifiedSpec->refresh()->status)->toBe(RecordSpecStatus::Verified)
        ->and(app(StructuredSpecs::class)->find(Area::Fauna, 'FN0001')?->range('gh'))->toBe(['min' => 8.0, 'max' => 30.0]);
});
