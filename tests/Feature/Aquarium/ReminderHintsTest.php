<?php

use App\Domain\Aquarium\Actions\SuggestReminders;
use App\Domain\Aquarium\Models\Equipment;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\ReminderType;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Record;
use App\Livewire\Aquarium\ReminderSuggestions;
use App\Livewire\Aquarium\TankReminders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

function stockTank($user, array $fauna = [], array $flora = [], array $equipment = []): int
{
    $tank = tankFor($user);
    foreach ([[Area::Fauna, $fauna], [Area::Flora, $flora]] as [$area, $ids]) {
        foreach ($ids as $id) {
            Livestock::create(['tank_id' => $tank->id, 'area' => $area, 'catalog_id' => $id, 'quantity' => 1]);
        }
    }
    foreach ($equipment as $id) {
        Equipment::create(['tank_id' => $tank->id, 'area' => Area::Equipment, 'catalog_id' => $id]);
    }

    return $tank->id;
}

it('suggests a water change from a fauna description sentence and shows it as evidence', function () {
    $user = aquarist(true);
    $tankId = stockTank($user, ['FN0001']);

    $hints = app(SuggestReminders::class)->handle($user, $tankId);

    expect($hints)->toHaveCount(1)->and($hints[0]->type)->toBe(ReminderType::WaterChange);
    $evidence = $hints[0]->sources[0]['evidence'];
    expect($evidence->recordId)->toBe('FN0001')->and($evidence->column)->toBe('opis')
        ->and($evidence->value)->toContain('regularn')->and($evidence->value)->toContain('podmian');

    $this->actingAs($user)->get(route('aquarium.tank', ['tank' => $tankId, 'karta' => 'podpowiedzi']))->assertOk()
        ->assertSee('Podmiana wody')->assertSee('Dlaczego?')->assertSee('baza: opis = ', false)
        ->assertSee('typ=water_change', false)->assertDontSee('co 7 dni');
});

it('suggests nothing when no record names an action', function () {
    $user = aquarist(true);
    $empty = stockTank($user);
    expect(app(SuggestReminders::class)->handle($user, $empty))->toBe([]);

    $row = Record::where('legacy_id', 'FN0001')->firstOrFail();
    $row->description()->updateOrCreate([], ['body' => 'Gupik to ryba. Podmiany bywają różne, a nawożenie nie dotyczy.']);
    $tankId = stockTank($user, ['FN0001']);

    expect(app(SuggestReminders::class)->handle($user, $tankId))->toBe([]);
    Livewire::actingAs($user)->test(ReminderSuggestions::class, ['tankId' => $tankId])->assertSee('Brak podpowiedzi');
});

it('suggests fertilization from a flora description and filter cleaning from a filter record, one hint per type', function () {
    $user = aquarist(true);
    $florae = collect(app(Catalog::class)->snapshot(Area::Flora, withDescriptions: true))
        ->filter(fn ($e) => preg_match('/regularn[^.]*nawoż|nawoż[^.]*regularn/iu', (string) $e->description))->take(2)->pluck('id')->all();
    $tankId = stockTank($user, ['FN0001'], $florae, ['SP0108', 'SP0001']);

    $hints = collect(app(SuggestReminders::class)->handle($user, $tankId))->keyBy(fn ($h) => $h->type->value);

    expect($hints->keys()->all())->toBe(['water_change', 'filter_cleaning', 'fertilization'])
        ->and($hints['fertilization']->sources)->toHaveCount(count($florae))
        ->and($hints['filter_cleaning']->sources)->toHaveCount(1)
        ->and($hints['filter_cleaning']->sources[0]['evidence']->recordId)->toBe('SP0108')
        ->and($hints['filter_cleaning']->sources[0]['evidence']->column)->toBe('opis_krotki');
    expect($florae)->not->toBeEmpty();
});

it('requires the Fishroom feature and the own tank', function () {
    $tank = stockTank($owner = aquarist(true), ['FN0001']);
    $tankTier = aquarist();
    $ownTank = stockTank($tankTier, ['FN0001']);

    expect(fn () => app(SuggestReminders::class)->handle(aquarist(true), $tank))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(SuggestReminders::class)->handle($tankTier, $ownTank))->toThrow(AuthorizationException::class);
});

it('opens the existing reminder form with the type filled and no default interval', function () {
    $user = aquarist(true);
    $tankId = stockTank($user, ['FN0001']);

    $page = $this->actingAs($user)->get(route('aquarium.tank', ['tank' => $tankId, 'karta' => 'przypomnienia', 'typ' => 'fertilization']))->assertOk();
    $page->assertSee('Nowe przypomnienie');
    expect($page->getContent())->toContain('&quot;type&quot;:&quot;fertilization&quot;');

    Livewire::withQueryParams(['typ' => 'filter_cleaning'])->actingAs($user)->test(TankReminders::class, ['tankId' => $tankId])
        ->assertSet('type', 'filter_cleaning')->assertSet('interval_days', '');
    Livewire::withQueryParams(['typ' => 'nieznany'])->actingAs($user)->test(TankReminders::class, ['tankId' => $tankId])
        ->assertSet('type', 'water_change');
});
