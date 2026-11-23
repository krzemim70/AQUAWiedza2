<?php

use App\Domain\Aquarium\Actions\BuildWaterHints;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Livewire\Aquarium\TankMeasurements;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('shows catalogue evidence after saving and when revisiting the tank', function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::WaterHintsMap, [
        ['problem' => 'temperature_low', 'area' => 'equipment', 'id' => null, 'category' => 'Ogrzewanie', 'phrase' => null],
    ]);
    $user = aquarist();
    $tank = tankFor($user);
    Livestock::create(['tank_id' => $tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0001', 'quantity' => 1]);

    Livewire::actingAs($user)->test(TankMeasurements::class, ['tankId' => $tank->id])
        ->set('ph', '7,9')->set('temp_c', '20')->call('save')->assertHasNoErrors()
        ->assertSee('Pomiar zapisany')->assertSee('Źródła w katalogu się różnią')
        ->assertSee('Temperatura 20 °C — poniżej zakresu podanego w katalogu')
        ->assertSee('Dlaczego?')->assertDontSee('Acid Buffer')->assertDontSee('Ogrzewanie');

    $this->actingAs($user)->get(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'pomiary']))
        ->assertOk()->assertSee('Porównanie pomiaru z katalogiem')->assertSee('pH 7,0–8,5');

    Livestock::create(['tank_id' => $tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0117', 'quantity' => 1]);
    $this->get(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'pomiary']))
        ->assertOk()->assertSee('pasma w katalogu nie mają części wspólnej')
        ->assertSeeHtml(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'obsada']).'#compat-heading');
});

it('shows only age and missing data for an old measurement with flora only', function () {
    $this->freezeTime();
    $user = aquarist();
    $tank = tankFor($user);
    Livestock::create(['tank_id' => $tank->id, 'area' => Area::Flora, 'catalog_id' => 'FL0011', 'quantity' => 1]);
    measure($user, $tank->id, ['ph' => '7.9'], now()->subDays(30));

    Livewire::actingAs($user)->test(TankMeasurements::class, ['tankId' => $tank->id])
        ->assertSee('Wiek pomiaru (dni): 30')->assertSee('brak danych katalogowych dla tej obsady')
        ->assertDontSee('pH Test')->assertDontSee('Do koszyka');

    shopSetting(SettingKey::WaterHintsStaleDays, 31);
    $report = app(BuildWaterHints::class)->handle($user, $tank->id);
    expect($report->ageInDays)->toBe(30)->and($report->stale)->toBeFalse();
});

it('returns no report without a measurement and refuses access to another owner’s tank', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $action = app(BuildWaterHints::class);

    expect($action->handle($user, $tank->id))->toBeNull()
        ->and(fn () => $action->handle(aquarist(), $tank->id))->toThrow(ModelNotFoundException::class);
});
