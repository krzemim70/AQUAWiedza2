<?php

use App\Domain\KnowledgeBase\Actions\ActivateBatch;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\ImportResult;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\KnowledgeBase\Models\Description;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\KnowledgeBase\Models\RegistryEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function kbImport(string $dir): ImportResult
{
    return app(ImportPackage::class)->handle($dir);
}

/** Zmienia treść pliku paczki (podmiana tekstu). */
function kbEdit(string $dir, string $file, string $search, string $replace): void
{
    $path = $dir.DIRECTORY_SEPARATOR.$file;
    file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
}

it('imports the reference package: 200/194/108/110 active records with descriptions', function () {
    $result = kbImport(kbPackage());

    expect($result->status)->toBe(ImportResult::IMPORTED)
        ->and($result->batch->state)->toBe(Batch::ACTIVE)
        ->and($result->batch->counts)->toEqual(['fauna' => 200, 'flora' => 194, 'equipment' => 108, 'care' => 110]);

    $active = Batch::query()->active()->sole();
    expect($active->id)->toBe($result->batch->id)
        ->and(Entry::where('batch_id', $active->id)->count())->toBe(612)
        ->and(Record::count())->toBe(612)
        ->and(Description::count())->toBe(394)
        ->and(Photo::count())->toBe(190)
        ->and(Photo::where('verified', true)->count())->toBe(0)
        ->and(RegistryEntry::where('status', 'active')->count())->toBe(394 + 108 + 110)
        ->and(RegistryEntry::where('status', 'future')->count())->toBe(30)
        ->and(RegistryEntry::where('status', 'retired')->count())->toBe(1);

    foreach (Area::cases() as $area) {
        expect(app(Catalog::class)->snapshot($area))->toHaveCount($result->batch->counts[$area->value]);
    }
});

it('keeps the file column order of flora, which differs from fauna', function () {
    kbImport(kbPackage());

    $fauna = app(Catalog::class)->find(Area::Fauna, 'FN0001');
    $flora = app(Catalog::class)->find(Area::Flora, 'FL0001');

    expect($fauna->name)->toBe('Gupik Pawie Oczko')->and($fauna->nameLatin)->toBe('Poecilia reticulata')
        ->and($flora->name)->toBe('Rotala okrągłolistna')->and($flora->nameLatin)->toBe('Rotala rotundifolia')
        ->and($flora->values['TypAkwarium'])->toBe(['Ogólne / Towarzyskie'])
        ->and($flora->values['WielkośćAkwarium'])->toHaveCount(3);
});

it('handles the guard line, BOM and missing guard variants', function () {
    $dir = kbPackage();
    $file = $dir.'/baza_pielegnacja_end1.txt.php';
    $body = preg_replace('/^<\?php exit; \?>\R/', '', file_get_contents($file));
    // BOM przed strażnikiem, strażnik z LF, BOM przed nagłówkiem
    file_put_contents($file, "\xEF\xBB\xBF<?php exit; ?>\n\xEF\xBB\xBF".$body);
    // brak strażnika w drugim pliku
    $flora = $dir.'/baza_flora_end1.txt.php';
    file_put_contents($flora, preg_replace('/^<\?php exit; \?>\R/', '', file_get_contents($flora)));

    $result = kbImport($dir);

    expect($result->status)->toBe(ImportResult::IMPORTED)
        ->and($result->batch->counts['care'])->toBe(110)
        ->and($result->batch->counts['flora'])->toBe(194);
});

it('rejects a package with wrong headers, bad rows and orphan descriptions, reporting file and row', function () {
    $dir = kbPackage();
    kbEdit($dir, 'baza_fauna_end1.txt.php', "Nazwa polska\tNazwa łacińska", "Nazwa łacińska\tNazwa polska");
    kbEdit($dir, 'baza_flora_end1.txt.php', "FL0003\t", "FL0002\t");
    kbEdit($dir, 'baza_biotop_end2.txt.php', "SP0002\tFiltracja", 'SP0002');
    kbEdit($dir, 'opisy_flora.json.php', '"FL0010"', '"FL9999"');

    $result = kbImport($dir);
    $errors = implode("\n", $result->errors);

    expect($result->status)->toBe(ImportResult::FAILED)
        ->and($errors)->toContain('baza_fauna_end1.txt.php: nagłówki')
        ->and($errors)->toContain('baza_flora_end1.txt.php: wiersz 4: zduplikowany identyfikator FL0002')
        ->and($errors)->toContain('baza_biotop_end2.txt.php: wiersz 3:')
        ->and($errors)->toContain("opisy_flora.json.php: pozycja 10: opis osieroconego rekordu 'FL9999'");
});

it('reports missing files and ids absent from the registry', function () {
    $dir = kbPackage();
    unlink($dir.'/rejestr_id.json.php');
    expect(kbImport($dir)->errors)->toContain('rejestr_id.json.php: brak pliku lub brak odczytu');

    $dir = kbPackage();
    kbEdit($dir, 'baza_fauna_end1.txt.php', "FN0005\t", "FN0999\t");
    expect(implode("\n", kbImport($dir)->errors))->toContain('identyfikator FN0999 nie jest aktywny w rejestrze');
});

it('leaves the active batch and data untouched when a later package fails', function () {
    $good = kbImport(kbPackage());
    $entries = Entry::count();

    $dir = kbPackage();
    kbEdit($dir, 'baza_fauna_end1.txt.php', "FN0002\t", "FN0001\t");
    $failed = kbImport($dir);

    expect($failed->status)->toBe(ImportResult::FAILED)
        ->and($failed->batch->state)->toBe(Batch::FAILED)
        ->and($failed->batch->checksum)->toBeNull()
        ->and(Batch::query()->active()->sole()->id)->toBe($good->batch->id)
        ->and(Entry::count())->toBe($entries)
        ->and(Record::count())->toBe(612);
});

it('is a no-op when the same package is imported again', function () {
    $first = kbImport(kbPackage());
    $again = kbImport(kbPackage());

    expect($again->status)->toBe(ImportResult::UNCHANGED)
        ->and($again->batch->id)->toBe($first->batch->id)
        ->and(Batch::count())->toBe(1)
        ->and(Entry::count())->toBe(612);
});

it('switches the active batch atomically and can restore the previous one', function () {
    $first = kbImport(kbPackage());

    $dir = kbPackage();
    kbEdit($dir, 'baza_biotop_end2.txt.php', 'Ultramax 1000', 'Ultramax 1001');
    $second = kbImport($dir);
    $catalog = app(Catalog::class);

    expect($second->status)->toBe(ImportResult::IMPORTED)
        ->and($first->batch->refresh()->state)->toBe(Batch::SUPERSEDED)
        ->and($first->batch->active_slot)->toBeNull()
        ->and($catalog->find(Area::Equipment, 'SP0001')->name)->toBe('Ultramax 1001')
        ->and(Record::count())->toBe(612);

    app(ActivateBatch::class)->handle($first->batch);

    expect(Batch::query()->active()->sole()->id)->toBe($first->batch->id)
        ->and($second->batch->refresh()->state)->toBe(Batch::SUPERSEDED)
        ->and($catalog->find(Area::Equipment, 'SP0001')->name)->toBe('Ultramax 1000');

    // baza nie pozwala na dwie aktywne partie
    expect(fn () => $second->batch->forceFill(['active_slot' => 1])->save())->toThrow(QueryException::class);
});

it('refuses to activate a failed batch', function () {
    $dir = kbPackage();
    unlink($dir.'/opisy_fauna.json.php');
    $failed = kbImport($dir)->batch;

    expect(fn () => app(ActivateBatch::class)->handle($failed))->toThrow(DomainException::class);
});

it('never reuses or drops registry ids', function () {
    kbImport(kbPackage());

    // rejestr nie pozwala usunąć identyfikatora
    expect(fn () => RegistryEntry::where('legacy_id', 'FN0001')->firstOrFail()->delete())->toThrow(LogicException::class);

    // wycofany identyfikator nie wraca do puli: paczka z tym rekordem jest odrzucona
    RegistryEntry::where('legacy_id', 'FN0001')->update(['status' => 'retired']);
    $dir = kbPackage();
    kbEdit($dir, 'baza_biotop_end2.txt.php', 'Ultramax 1000', 'Ultramax 1001');
    $result = kbImport($dir);

    expect($result->status)->toBe(ImportResult::FAILED)
        ->and(implode("\n", $result->errors))->toContain('identyfikator FN0001 został wycofany');

    // rekord nie istnieje bez wpisu w rejestrze (klucz obcy)
    expect(fn () => DB::table('catalog_records')->insert(['area' => 'fauna', 'legacy_id' => 'FN7777', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('does not overwrite a manually edited description on a later import', function () {
    kbImport(kbPackage());
    $record = Record::where('legacy_id', 'FN0001')->firstOrFail();
    $record->description->update(['body' => 'Własny opis.', 'edited_at' => now()]);

    $dir = kbPackage();
    kbEdit($dir, 'baza_biotop_end2.txt.php', 'Ultramax 1000', 'Ultramax 1001');
    kbImport($dir);

    expect($record->description()->first()->body)->toBe('Własny opis.');
});

it('imports from the console', function () {
    $this->artisan('kb:import', ['path' => kbPackage()])->assertSuccessful();
    $this->artisan('kb:import', ['path' => kbPackage()])->expectsOutputToContain('bez zmian')->assertSuccessful();

    $broken = kbPackage();
    unlink($broken.'/baza_flora_end1.txt.php');
    $this->artisan('kb:import', ['path' => $broken])->expectsOutputToContain('baza_flora_end1.txt.php')->assertFailed();
});

it('matches the fixture manifest byte for byte', function () {
    $manifest = json_decode(file_get_contents(base_path('tests/Fixtures/KnowledgeBase/manifest.json')), true);

    expect($manifest)->toHaveCount(8);
    foreach ($manifest as $item) {
        expect(hash_file('sha256', base_path('tests/Fixtures/KnowledgeBase/'.$item['filename'])))->toBe($item['sha256']);
    }
});
