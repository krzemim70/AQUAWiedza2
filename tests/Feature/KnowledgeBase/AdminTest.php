<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Actions\SavePhoto;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Filament\Resources\Batches\Pages\ListBatches;
use App\Filament\Resources\Entries\Pages\ListEntries;
use App\Filament\Resources\Entries\Pages\ViewEntry;
use App\Filament\Resources\Entries\RelationManagers\PhotosRelationManager;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->first = app(ImportPackage::class)->handle(kbPackage())->batch;
});

function kbEntry(string $id): Entry
{
    return Entry::query()->inActiveBatch()->whereHas('record', fn ($q) => $q->where('legacy_id', $id))->firstOrFail();
}

it('lists records of the active batch with search and filters (area, search, photo)', function () {
    $this->actingAs(User::factory()->admin()->create());
    $guppy = kbEntry('FN0001');
    $rotala = kbEntry('FL0001');

    Livewire::test(ListEntries::class)
        ->assertCanSeeTableRecords([$guppy, $rotala])
        ->filterTable('area', 'flora')->assertCanSeeTableRecords([$rotala])->assertCanNotSeeTableRecords([$guppy])
        ->resetTableFilters()->searchTable('okraglolistna')->assertCanSeeTableRecords([$rotala])->assertCanNotSeeTableRecords([$guppy])
        ->searchTable('FN0001')->assertCanSeeTableRecords([$guppy])->assertCanNotSeeTableRecords([$rotala])
        ->searchTable('')->filterTable('photo', true)->assertCanNotSeeTableRecords([$guppy])
        ->resetTableFilters()->filterTable('description', false)->assertCanNotSeeTableRecords([$guppy, $rotala]);
});

it('shows a record with attributes and description and edits the description', function () {
    $this->actingAs($admin = User::factory()->admin()->create());
    $entry = kbEntry('FN0001');

    Livewire::test(ViewEntry::class, ['record' => $entry->getKey()])
        ->assertSee('Gupik Pawie Oczko')
        ->assertSee('Poecilia reticulata')
        ->assertSee('Gupik pawie oczko (Poecilia reticulata)')
        ->callAction('editDescription', ['body' => 'Nowy opis gupika.', 'source_ref' => 'Redakcja'])
        ->assertHasNoActionErrors()
        ->assertSee('Nowy opis gupika.');

    $description = $entry->record->description()->first();
    expect($description->body)->toBe('Nowy opis gupika.')->and($description->edited_by)->toBe($admin->id);
});

it('adds and verifies photo metadata through the relation manager', function () {
    $this->actingAs(User::factory()->admin()->create());
    $entry = kbEntry('FL0001'); // flora nie ma zdjęć w danych

    $page = Livewire::test(PhotosRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => ViewEntry::class])
        ->callAction(TestAction::make('add')->table(), ['url' => 'https://example.test/a.jpg', 'author' => 'Autor', 'licence' => 'CC', 'verified' => true]);

    $photo = Photo::where('record_id', $entry->record_id)->sole();
    expect($photo->verified)->toBeTrue()->and($photo->verified_at)->not->toBeNull();

    $page->callAction(TestAction::make('edit')->table($photo), ['url' => 'https://example.test/a.jpg', 'verified' => false]);
    expect($photo->refresh()->verified)->toBeFalse()->and($photo->verified_at)->toBeNull();
});

it('lists import batches and restores a previous one', function () {
    $this->actingAs(User::factory()->admin()->create());
    $dir = kbPackage();
    $path = $dir.'/baza_biotop_end2.txt.php';
    file_put_contents($path, str_replace('Ultramax 1000', 'Ultramax 1001', file_get_contents($path)));
    $second = app(ImportPackage::class)->handle($dir)->batch;

    Livewire::test(ListBatches::class)
        ->assertCanSeeTableRecords([$this->first, $second])
        ->assertTableActionHidden('activate', $second)
        ->assertTableActionVisible('activate', $this->first)
        ->callTableAction('activate', $this->first);

    expect(Batch::query()->active()->sole()->id)->toBe($this->first->id);
});

it('forbids non-admins', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ListEntries::class)->assertForbidden();
    Livewire::test(ListBatches::class)->assertForbidden();
});

it('requires a URL or a file path for a photo and an admin for every write', function () {
    $entry = kbEntry('FL0001');

    expect(fn () => app(SavePhoto::class)->handle(User::factory()->admin()->create(), $entry->record, ['author' => 'A']))->toThrow(ValidationException::class)
        ->and(fn () => app(SavePhoto::class)->handle(User::factory()->create(), $entry->record, ['url' => 'https://example.test/a.jpg']))->toThrow(AuthorizationException::class);
});
