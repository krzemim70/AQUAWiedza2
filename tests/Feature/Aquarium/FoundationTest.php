<?php

use App\Domain\Advisor\Engine\Co2Need;
use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Advisor\Engine\Temperament;
use App\Domain\Advisor\Engine\Zone;
use App\Domain\Advisor\FaunaPool;
use App\Domain\Advisor\FloraPool;
use App\Domain\Aquarium\Models\Equipment;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Livewire\Aquarium\TankShow;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('adds the shared columns and the equipment table', function () {
    expect(Schema::hasColumn('users', 'city'))->toBeTrue()
        ->and(Schema::hasColumn('tanks', 'volume_liters'))->toBeTrue()
        ->and(Schema::hasColumns('tank_equipment', ['tank_id', 'area', 'catalog_id', 'installed_on']))->toBeTrue();
});

it('keeps tank volume and city empty by default and stores equipment per tank', function () {
    $user = aquarist();
    $tank = tankFor($user);

    expect($user->fresh()->city)->toBeNull()
        ->and($tank->fresh()->volume_liters)->toBeNull();

    $tank->update(['volume_liters' => 63]);
    $tank->equipment()->create(['area' => Area::Equipment, 'catalog_id' => 'SP0046']);
    expect($tank->fresh()->volume_liters)->toBe(63)
        ->and($tank->equipment)->toHaveCount(1)
        ->and($tank->equipment->first()->area)->toBe(Area::Equipment);

    $tank->delete();
    expect(Equipment::count())->toBe(0);
});

it('reads temperament, zone and CO2 from the knowledge base', function () {
    app(ImportPackage::class)->handle(kbPackage());
    $fauna = collect(app(FaunaPool::class)->records())->keyBy('id');
    $flora = collect(app(FloraPool::class)->records())->keyBy('id');

    expect($fauna['FN0090']->temperament)->toBe(Temperament::Aggressive)
        ->and($fauna['FN0001']->temperament)->toBe(Temperament::Calm)
        ->and($fauna['FN0001']->zone)->toBe(Zone::Top)
        ->and($fauna->filter(fn ($r) => $r->temperament === null || $r->zone === null))->toBeEmpty()
        ->and($flora)->toHaveCount(194)
        ->and($flora['FL0011']->co2)->toBe(Co2Need::Required)
        ->and($flora->filter(fn ($r) => $r->co2 === null))->toBeEmpty();
});

it('renders the evidence under "Dlaczego?"', function () {
    $html = Blade::render('<x-why :evidence="$e" />', ['e' => [new Evidence('Temperatura', 'Grzałka często (22-25,5)', 'FN0001', 3)]]);

    expect($html)->toContain('Dlaczego?')->toContain('baza: Temperatura = Grzałka często (22-25,5) (FN0001)')->toContain('partia bazy 3')
        ->and(trim(Blade::render('<x-why :evidence="[]" />')))->toBe('');
});

it('lists the tank tabs from the tab files and marks Fishroom tabs for Tank', function () {
    $user = aquarist();
    $tank = tankFor($user);

    $page = Livewire::actingAs($user)->test(TankShow::class, ['tank' => $tank->id]);
    foreach (['Zbiornik', 'Obsada', 'Pomiary', 'Historia parametrów', 'Dziennik', 'Przypomnienia'] as $label) {
        $page->assertSee($label);
    }
    expect(substr_count($page->html(), '(Fishroom)'))->toBe(3);
});
