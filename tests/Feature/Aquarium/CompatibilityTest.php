<?php

use App\Domain\Aquarium\Actions\AddToLivestock;
use App\Domain\Aquarium\Actions\CheckLivestockFit;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\LivestockFitWarning;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Livewire\Aquarium\TankLivestock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

function compatTank($user, string ...$ids): int
{
    $tank = tankFor($user);
    foreach ($ids as $id) {
        Livestock::create(['tank_id' => $tank->id, 'area' => Area::Fauna, 'catalog_id' => $id, 'quantity' => 1]);
    }

    return $tank->id;
}

it('throws a warning without saving, then adds with acknowledgement and remembers the codes', function () {
    $user = aquarist();
    $tankId = compatTank($user, 'FN0001');
    $data = new LivestockData(Area::Fauna, 'FN0011', 2);

    expect(fn () => app(AddToLivestock::class)->handle($user, $tankId, $data))->toThrow(LivestockFitWarning::class);
    expect(Livestock::count())->toBe(1);

    app(AddToLivestock::class)->handle($user, $tankId, $data, acknowledge: true);
    $row = Livestock::where('catalog_id', 'FN0011')->sole();
    expect($row->quantity)->toBe(2)->and($row->acknowledged_findings)->toContain('temperature_pair:FN0001:FN0011');

    // to samo ustalenie nie pyta drugi raz
    app(AddToLivestock::class)->handle($user, $tankId, new LivestockData(Area::Fauna, 'FN0011', 1));
    expect($row->fresh()->quantity)->toBe(3);
});

it('adds without a dialog when the catalogue has only informational entries', function () {
    $user = aquarist();
    $tankId = compatTank($user, 'FN0001');

    app(AddToLivestock::class)->handle($user, $tankId, new LivestockData(Area::Fauna, 'FN0150', 1));

    expect(Livestock::where('catalog_id', 'FN0150')->sole()->acknowledged_findings)->toBeNull();
});

it('shows the confirmation page from the product/advisor form and adds after "add anyway"', function () {
    $user = aquarist();
    $tankId = compatTank($user, 'FN0001');
    $payload = ['tank' => $tankId, 'area' => 'fauna', 'species' => 'FN0011', 'quantity' => 1, 'request_key' => (string) Str::uuid(), 'return_to' => '/katalog/fauna/FN0011'];

    $this->actingAs($user)->post(route('advisor.add-to-tank'), $payload)->assertOk()
        ->assertSee('Uwagi przed dodaniem')->assertSee('Dodaj mimo to')->assertSee('Dlaczego?')->assertSee('Anuluj')
        ->assertSee('Grzałka sporadycznie (18-21,5)');
    expect(Livestock::count())->toBe(1);

    $this->post(route('advisor.add-to-tank'), $payload + ['acknowledge' => 1])->assertRedirect('/katalog/fauna/FN0011');
    expect(Livestock::count())->toBe(2);
});

it('shows the confirmation page for the winner form of the tank advisor', function () {
    $user = aquarist();
    $tankId = compatTank($user, 'FN0001');

    $this->actingAs($user)->post(route('advisor.livestock'), ['tank' => $tankId, 'species' => 'FN0011'])->assertOk()->assertSee('Dodaj mimo to');
    $this->post(route('advisor.livestock'), ['tank' => $tankId, 'species' => 'FN0011', 'acknowledge' => 1])->assertRedirect();
    expect(Livestock::count())->toBe(2);
});

it('asks for confirmation in the tank panel and adds on confirm or drops on cancel', function () {
    $user = aquarist();
    $tankId = compatTank($user, 'FN0001');

    $component = Livewire::actingAs($user)->test(TankLivestock::class, ['tankId' => $tankId])
        ->call('add', 'fauna', 'FN0011')->assertSee('Uwagi przed dodaniem')->assertSee('Dodaj mimo to');
    expect(Livestock::count())->toBe(1);

    $component->call('cancelPending')->assertDontSee('Uwagi przed dodaniem');
    expect(Livestock::count())->toBe(1);

    $component->call('add', 'fauna', 'FN0011')->call('confirmPending')->assertDontSee('Uwagi przed dodaniem');
    expect(Livestock::count())->toBe(2);
});

it('renders catalogue comparisons, missing data and temperament labels', function () {
    $user = aquarist(fishroom: true);
    $clear = compatTank($user, 'FN0001', 'FN0150');
    $differences = compatTank($user, 'FN0001', 'FN0011', 'FN0090');
    $incomplete = compatTank($user, 'FN0001', 'FN0165', 'FN9999');

    Livewire::actingAs($user)->test(TankLivestock::class, ['tankId' => $clear])->assertSee('Porównanie wpisów katalogu')->assertSee('Kontrole zgodności obsady')
        ->assertDontSee('%')->assertDontSee('zdrowie');
    Livewire::actingAs($user)->test(TankLivestock::class, ['tankId' => $differences])->assertSee('Pasma temperatury w katalogu się różnią')
        ->assertSee('temperament w katalogu: Agresywny/Terytorialny')->assertSee('Relacja pary: brak danych')->assertSee('Dlaczego?');
    Livewire::actingAs($user)->test(TankLivestock::class, ['tankId' => $incomplete])->assertSee('Temperatura (pary gatunków)')
        ->assertSee('Brak danych katalogowych dla 1 pozycji obsady')->assertDontSee('Są konflikty');
});

it('shows missing tank size next to the catalogue entry', function () {
    $user = aquarist();
    $tankId = compatTank($user, 'FN0001');
    DB::table('tanks')->where('id', $tankId)->update(['size_band' => null]);

    Livewire::actingAs($user)->test(TankLivestock::class, ['tankId' => $tankId])->assertSee('Wybrane pasmo zbiornika: brak danych')
        ->assertSee('wielkość akwarium w katalogu: Małe');
});

it('counts notes per candidate for the ranking badge and does not touch foreign tanks', function () {
    $user = aquarist();
    $tankId = compatTank($user, 'FN0001');
    $tank = Tank::find($tankId);

    expect(app(CheckLivestockFit::class)->notes($user, $tank, ['FN0011', 'FN0150']))->toBe(['FN0011' => 1, 'FN0150' => 0]);

    $this->actingAs($user)->get(route('advisor.tank', ['tank' => $tankId]))->assertOk()->assertSee('Uwagi dla Twojej obsady');
    expect(fn () => app(CheckLivestockFit::class)->handle(aquarist(), $tankId))->toThrow(ModelNotFoundException::class);
});
