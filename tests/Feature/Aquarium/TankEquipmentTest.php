<?php

use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Aquarium\Actions\ConvertPlanToTank;
use App\Domain\Aquarium\Actions\CreatePlan;
use App\Domain\Aquarium\Data\PlanData;
use App\Domain\Aquarium\Models\Equipment;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

it('searches equipment by name and id and adds only equipment records', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $tab = ['tank' => $tank->id, 'karta' => 'wyposazenie'];
    $this->actingAs($user);

    foreach (['Fluval Micro', 'sp0108'] as $phrase) {
        $this->get(route('aquarium.tank', $tab + ['szukaj' => $phrase]))->assertOk()
            ->assertSee('Nazwa lub ID sprzętu')->assertSee('Fluval Micro')->assertSee('SP0108');
    }
    $this->get(route('aquarium.tank', $tab + ['szukaj' => 'FN0001']))->assertOk()->assertSee('Brak wyników.');

    $url = route('aquarium.equipment.store');
    $this->post($url, ['tank' => $tank->id, 'catalog_id' => 'FN0001', 'quantity' => 2])
        ->assertSessionHasErrors('catalog_id', null, 'equipment');
    $this->post($url, ['tank' => $tank->id, 'catalog_id' => 'SP0108', 'quantity' => '2.5'])
        ->assertSessionHasErrors('quantity', null, 'equipment');
    $this->assertDatabaseCount('tank_equipment', 0);

    $this->post($url, ['tank' => $tank->id, 'catalog_id' => 'SP0108', 'quantity' => 2])
        ->assertRedirect(route('aquarium.tank', $tab));
    $row = $tank->equipment()->sole();
    expect($row->area)->toBe(Area::Equipment)->and($row->quantity)->toBe(2);
    $this->get(route('aquarium.tank', $tab))->assertOk()->assertSee('Dodano sprzęt do zbiornika.')
        ->assertSee(route('catalog.show', ['equipment', 'SP0108']), false);
});

it('updates the quantity and removes the position by explicit request', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $row = $tank->equipment()->create(['area' => Area::Equipment, 'catalog_id' => 'SP0108']);
    expect($row->fresh()->quantity)->toBe(1);

    $url = route('aquarium.equipment.update', [$tank->id, $row->id]);
    $tab = route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'wyposazenie']);
    $this->actingAs($user)->patch($url, ['quantity' => 0])->assertSessionHasErrors('quantity', null, 'equipment-'.$row->id);
    $this->patch($url, ['quantity' => 7])->assertRedirect($tab);
    expect($row->fresh()->quantity)->toBe(7);

    $this->delete($url)->assertRedirect($tab);
    $this->assertDatabaseMissing('tank_equipment', ['id' => $row->id]);
});

it('blocks reading and writing another user tank and scoping their position to an owned tank', function () {
    $owner = aquarist();
    $other = aquarist();
    $tank = tankFor($owner);
    $otherTank = tankFor($other);
    $row = $tank->equipment()->create(['area' => Area::Equipment, 'catalog_id' => 'SP0108', 'quantity' => 2]);
    $this->actingAs($other)->get(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'wyposazenie']))->assertNotFound();
    $this->post(route('aquarium.equipment.store'), ['tank' => $tank->id, 'catalog_id' => 'SP0001', 'quantity' => 1])->assertNotFound();

    foreach ([$tank, $otherTank] as $target) {
        $url = route('aquarium.equipment.update', [$target->id, $row->id]);
        $this->patch($url, ['quantity' => 3])->assertNotFound();
        $this->delete($url)->assertNotFound();
    }
    expect($row->fresh()->quantity)->toBe(2);
    $this->assertDatabaseCount('tank_equipment', 1);
});

it('limits positions and allows editing at the limit without creating duplicates', function () {
    shopSetting(SettingKey::EquipmentLimit, 1);
    $user = aquarist();
    $tank = tankFor($user);
    $url = route('aquarium.equipment.store');
    $filter = ['tank' => $tank->id, 'catalog_id' => 'SP0108', 'quantity' => 2];
    $this->actingAs($user)->post($url, $filter)->assertSessionHasNoErrors();
    $this->post($url, $filter)->assertSessionHasErrors('catalog_id', null, 'equipment');
    $this->post($url, array_replace($filter, ['catalog_id' => 'SP0001']))->assertSessionHasErrors('equipment', null, 'equipment');

    $row = $tank->equipment()->sole();
    $this->patch(route('aquarium.equipment.update', [$tank->id, $row->id]), ['quantity' => 3])->assertSessionHasNoErrors();
    expect($row->fresh()->quantity)->toBe(3);
});

it('adds equipment from its product card and returns to the card', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $product = route('catalog.show', ['equipment', 'SP0108'], absolute: false);
    $url = route('aquarium.equipment.store');

    $this->actingAs($user)->get($product)->assertOk()->assertSee('Dodaj do zbiornika')
        ->assertSee($url, false)->assertSee('name="catalog_id" value="SP0108"', false);
    $this->post($url, ['tank' => $tank->id, 'catalog_id' => 'SP0108', 'quantity' => 2, 'return_to' => $product])
        ->assertRedirect($product);
    $this->get($product)->assertOk()->assertSee('Dodano sprzęt do zbiornika.');
    expect($tank->equipment()->sole()->quantity)->toBe(2);
});

it('shows the filter cleaning hint and source sentence after adding the fixture filter', function () {
    $user = aquarist(true);
    $tank = tankFor($user);
    $hints = route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'podpowiedzi']);
    $this->actingAs($user)->get($hints)->assertOk()->assertSee('Brak podpowiedzi')->assertDontSee('typ=filter_cleaning', false);

    $this->post(route('aquarium.equipment.store'), ['tank' => $tank->id, 'catalog_id' => 'SP0108', 'quantity' => 1])
        ->assertSessionHasNoErrors();
    $this->get($hints)->assertOk()->assertSee('Dlaczego?')->assertSee('SP0108')
        ->assertSee('Cichy, wydajny, łatwy w czyszczeniu.')->assertSee('typ=filter_cleaning', false);
});

it('moves stored equipment and livestock into their respective tables when converting a plan', function () {
    $user = aquarist();
    $plan = app(CreatePlan::class)->handle($user, new PlanData('Plan ze sprzętem', 'Ogólne / Towarzyskie', TankBand::Medium->value));
    $plan->items()->createMany([
        ['area' => Area::Equipment, 'catalog_id' => 'SP0108', 'quantity' => 3],
        ['area' => Area::Fauna, 'catalog_id' => 'FN0001', 'quantity' => 5],
    ]);
    $convert = app(ConvertPlanToTank::class);
    $tank = $convert->handle($user, $plan->id);

    expect($tank->equipment()->pluck('quantity', 'catalog_id')->all())->toBe(['SP0108' => 3])
        ->and($tank->livestock()->pluck('quantity', 'catalog_id')->all())->toBe(['FN0001' => 5])
        ->and($convert->handle($user, $plan->id)->id)->toBe($tank->id)
        ->and($tank->equipment()->count())->toBe(1);
});

it('keeps a plan intact when its equipment exceeds the tank limit', function () {
    shopSetting(SettingKey::EquipmentLimit, 1);
    $user = aquarist();
    $plan = app(CreatePlan::class)->handle($user, new PlanData('Plan ze sprzętem', 'Ogólne / Towarzyskie', TankBand::Medium->value));
    $plan->items()->createMany([
        ['area' => Area::Equipment, 'catalog_id' => 'SP0108', 'quantity' => 1],
        ['area' => Area::Equipment, 'catalog_id' => 'SP0001', 'quantity' => 1],
    ]);

    expect(fn () => app(ConvertPlanToTank::class)->handle($user, $plan->id))->toThrow(ValidationException::class, 'Limit wyposażenia');
    expect(Tank::count())->toBe(0)->and(Equipment::count())->toBe(0)
        ->and($plan->fresh()->state)->toBe(TankPlan::DRAFT)->and($plan->items()->count())->toBe(2);
});

it('exports only the account equipment and removes it with the account', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $row = $tank->equipment()->create(['area' => Area::Equipment, 'catalog_id' => 'SP0108', 'quantity' => 3]);
    $otherRow = tankFor(aquarist())->equipment()->create(['area' => Area::Equipment, 'catalog_id' => 'SP0001']);

    $this->actingAs($user)->post(route('account.privacy.export'))->assertOk()
        ->assertJsonCount(1, 'tanks')->assertJsonCount(1, 'tanks.0.equipment')
        ->assertJsonPath('tanks.0.equipment.0.catalog_id', 'SP0108')->assertJsonPath('tanks.0.equipment.0.quantity', 3);
    $this->delete(route('account.privacy.delete'), ['current_password' => 'password', 'confirmation' => 'USUŃ'])->assertRedirect(route('login'));
    $this->assertDatabaseMissing('tank_equipment', ['id' => $row->id]);
    $this->assertDatabaseHas('tank_equipment', ['id' => $otherRow->id]);
});
